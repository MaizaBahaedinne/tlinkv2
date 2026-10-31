<?php

namespace App\Support\Messenger;

use App\Models\MessengerBlock;
use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\MessengerParticipant;
use App\Models\User;
use App\Support\ModuleCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * T.Link Messenger : conversations directes et de groupe entre membres.
 *
 * Le « temps réel » repose sur une interrogation légère et adaptative du navigateur (aucun démon à installer) :
 * chaque appel ne lit que les messages postérieurs au dernier reçu.
 */
final class Messenger
{
    public const MODULE = 'messenger.chat';

    public const MAX_BODY = 4000;

    public const MAX_GROUP_MEMBERS = 50;

    public const PAGE = 50;

    private const TYPING_TTL = 6;

    private static ?bool $ready = null;

    /** La messagerie est ouverte : module actif et tables migrées. */
    public static function enabled(): bool
    {
        self::$ready ??= Schema::hasTable('messenger_messages');

        return self::$ready && ModuleCatalog::isActive(self::MODULE);
    }

    // ───────────────────────── Droits ─────────────────────────

    public static function participant(MessengerConversation $conversation, User $user): ?MessengerParticipant
    {
        return MessengerParticipant::query()
            ->where('conversation_id', $conversation->id)->where('user_id', $user->id)->whereNull('left_at')->first();
    }

    public static function isBlockedBetween(int $a, int $b): bool
    {
        return MessengerBlock::query()
            ->where(fn ($query) => $query->where('blocker_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($query) => $query->where('blocker_id', $b)->where('blocked_id', $a))
            ->exists();
    }

    public static function canMessage(User $from, User $to): bool
    {
        return $from->id !== $to->id && $to->is_active && ! self::isBlockedBetween($from->id, $to->id);
    }

    /**
     * Raison pour laquelle le membre ne peut pas écrire ici (null = il peut). Les blocages ne sont pas révélés à la personne bloquée.
     */
    public static function restriction(MessengerConversation $conversation, User $user): ?string
    {
        if ($conversation->isGroup()) {
            return null;
        }

        $other = self::otherUser($conversation, $user);

        if ($other === null || ! $other->is_active) {
            return 'Ce membre n’est plus joignable.';
        }

        if (MessengerBlock::query()->where('blocker_id', $user->id)->where('blocked_id', $other->id)->exists()) {
            return 'Vous avez bloqué ce membre. Débloquez-le pour lui écrire.';
        }

        return MessengerBlock::query()->where('blocker_id', $other->id)->where('blocked_id', $user->id)->exists()
            ? 'Vous ne pouvez pas écrire à ce membre.'
            : null;
    }

    public static function block(User $blocker, User $blocked): void
    {
        if ($blocker->id !== $blocked->id) {
            MessengerBlock::query()->firstOrCreate(['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id]);
        }
    }

    public static function unblock(User $blocker, User $blocked): void
    {
        MessengerBlock::query()->where('blocker_id', $blocker->id)->where('blocked_id', $blocked->id)->delete();
    }

    public static function otherUser(MessengerConversation $conversation, User $me): ?User
    {
        return MessengerParticipant::query()->with('user.profile')
            ->where('conversation_id', $conversation->id)->where('user_id', '!=', $me->id)->first()?->user;
    }

    // ───────────────────────── Conversations ─────────────────────────

    /** Conversation directe avec ce membre (créée si besoin) ; null si l'écriture est impossible. */
    public static function direct(User $me, User $other): ?MessengerConversation
    {
        if ($me->id === $other->id) {
            return null;
        }

        $key = min($me->id, $other->id).'-'.max($me->id, $other->id);
        $conversation = MessengerConversation::query()->where('direct_key', $key)->first();

        if ($conversation === null) {
            if (! self::canMessage($me, $other)) {
                return null;
            }

            try {
                $conversation = MessengerConversation::query()->create(['type' => 'direct', 'created_by' => $me->id, 'direct_key' => $key]);
            } catch (QueryException) {
                $conversation = MessengerConversation::query()->where('direct_key', $key)->firstOrFail();
            }
        }

        foreach ([$me, $other] as $user) {
            $row = MessengerParticipant::query()->firstOrCreate(['conversation_id' => $conversation->id, 'user_id' => $user->id], ['role' => 'member']);

            if ($row->left_at !== null) {
                $row->update(['left_at' => null, 'last_read_message_id' => (int) $conversation->last_message_id]);
            }
        }

        return $conversation;
    }

    /**
     * @param  array<int, int|string>  $memberIds
     */
    public static function createGroup(User $creator, string $title, array $memberIds): MessengerConversation
    {
        $ids = collect($memberIds)->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => $id !== $creator->id)->unique()->values();

        $members = User::query()->whereIn('id', $ids)->where('is_active', true)->get()
            ->filter(fn (User $user): bool => ! self::isBlockedBetween($creator->id, $user->id));

        if ($members->isEmpty()) {
            throw ValidationException::withMessages(['members' => 'Choisissez au moins un membre à ajouter au groupe.']);
        }

        if ($members->count() >= self::MAX_GROUP_MEMBERS) {
            throw ValidationException::withMessages(['members' => 'Un groupe compte '.self::MAX_GROUP_MEMBERS.' membres au maximum.']);
        }

        return DB::transaction(function () use ($creator, $title, $members): MessengerConversation {
            $conversation = MessengerConversation::query()->create(['type' => 'group', 'title' => $title, 'created_by' => $creator->id]);
            $conversation->participants()->create(['user_id' => $creator->id, 'role' => 'admin']);

            foreach ($members as $member) {
                $conversation->participants()->create(['user_id' => $member->id, 'role' => 'member']);
            }

            return $conversation;
        });
    }

    public static function leave(MessengerConversation $conversation, User $user): void
    {
        MessengerParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $user->id)->update(['left_at' => now()]);
    }

    // ───────────────────────── Messages ─────────────────────────

    public static function send(MessengerConversation $conversation, User $sender, ?string $body, ?UploadedFile $image = null): MessengerMessage
    {
        $body = trim((string) $body);

        if ($body === '' && $image === null) {
            throw ValidationException::withMessages(['body' => 'Écrivez un message ou joignez une image.']);
        }

        $path = $image?->store('messenger/'.$conversation->id, 'local');

        $message = DB::transaction(function () use ($conversation, $sender, $body, $image, $path): MessengerMessage {
            $message = $conversation->messages()->create([
                'user_id' => $sender->id,
                'body' => $body === '' ? null : $body,
                'attachment_path' => $path ?: null,
                'attachment_name' => $image !== null ? mb_substr(preg_replace('/[^\pL\pN._ -]/u', '', $image->getClientOriginalName()) ?: 'image', 0, 120) : null,
            ]);

            $conversation->forceFill(['last_message_id' => $message->id, 'last_message_at' => $message->created_at])->save();
            MessengerParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $sender->id)->update(['last_read_message_id' => $message->id]);

            return $message;
        });

        Cache::forget(self::typingKey($conversation->id, $sender->id));

        return $message;
    }

    public static function deleteMessage(MessengerMessage $message): void
    {
        if ($message->attachment_path) {
            Storage::disk('local')->delete($message->attachment_path);
        }

        $message->update(['body' => null, 'attachment_path' => null, 'attachment_name' => null, 'deleted_at' => now()]);
    }

    public static function markRead(MessengerConversation $conversation, User $user, int $upTo): void
    {
        $upTo = min($upTo, (int) $conversation->last_message_id);

        if ($upTo > 0) {
            MessengerParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $user->id)
                ->where('last_read_message_id', '<', $upTo)->update(['last_read_message_id' => $upTo]);
        }
    }

    public static function typing(MessengerConversation $conversation, User $user): void
    {
        Cache::put(self::typingKey($conversation->id, $user->id), true, self::TYPING_TTL);
    }

    private static function typingKey(int $conversationId, int $userId): string
    {
        return 'messenger:typing:'.$conversationId.':'.$userId;
    }

    // ───────────────────────── Lecture (temps réel) ─────────────────────────

    /**
     * Messages d'une conversation. `after` : seulement les nouveaux ; `before` : page de messages plus anciens ;
     * ni l'un ni l'autre : les derniers messages. `since` : horodatage du dernier appel, pour les suppressions.
     *
     * @return array<string, mixed>
     */
    public static function poll(MessengerConversation $conversation, User $me, int $after = 0, int $before = 0, ?int $since = null): array
    {
        $query = MessengerMessage::query()->with('author.profile')->where('conversation_id', $conversation->id);
        $hasMore = false;

        if ($after > 0) {
            $messages = $query->where('id', '>', $after)->orderBy('id')->limit(100)->get();
        } else {
            if ($before > 0) {
                $query->where('id', '<', $before);
            }

            $page = $query->orderByDesc('id')->limit(self::PAGE + 1)->get();
            $hasMore = $page->count() > self::PAGE;
            $messages = $page->take(self::PAGE)->reverse()->values();
        }

        $participants = MessengerParticipant::query()->with('user.profile')
            ->where('conversation_id', $conversation->id)->whereNull('left_at')->get();
        $others = $participants->where('user_id', '!=', $me->id);

        $typingKeys = $others->mapWithKeys(fn (MessengerParticipant $row): array => [self::typingKey($conversation->id, $row->user_id) => $row->user_id]);
        $typing = collect(Cache::many($typingKeys->keys()->all()))->filter()->keys()
            ->map(fn (string $key): ?string => $others->firstWhere('user_id', $typingKeys[$key])?->user?->name)->filter()->values()->all();

        $payload = [
            'messages' => $messages->map(fn (MessengerMessage $message): array => self::present($message, $me))->all(),
            'deleted_ids' => $since === null || $after === 0 ? [] : MessengerMessage::query()
                ->where('conversation_id', $conversation->id)->where('id', '<=', $after)
                ->where('deleted_at', '>=', Carbon::createFromTimestamp($since)->subSeconds(3))->pluck('id')->all(),
            'reads' => $others->mapWithKeys(fn (MessengerParticipant $row): array => [$row->user_id => $row->last_read_message_id])->all(),
            'typing' => $typing,
            'has_more' => $hasMore,
            'server_time' => time(),
        ];

        if ($after === 0 && $before === 0) {
            $payload['conversation'] = [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'title' => self::title($conversation, $me, $participants),
                'avatar' => $conversation->isGroup() ? null : $others->first()?->user?->profile?->photoUrl(),
                'verified' => ! $conversation->isGroup() && $others->first()?->user?->profile?->identity_verified_at !== null,
                'members' => $participants->map(fn (MessengerParticipant $row): string => (string) $row->user?->name)->filter()->values()->all(),
                'other_id' => $conversation->isGroup() ? null : $others->first()?->user_id,
                'restriction' => self::restriction($conversation, $me),
                'blocked_by_me' => ! $conversation->isGroup() && $others->first() !== null
                    && MessengerBlock::query()->where('blocker_id', $me->id)->where('blocked_id', $others->first()->user_id)->exists(),
            ];
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public static function present(MessengerMessage $message, User $viewer): array
    {
        $deleted = $message->deleted_at !== null;
        $author = $message->author;

        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'author' => $author?->name ?? 'Membre supprimé',
            'avatar' => $author?->profile?->photoUrl(),
            'verified' => $author?->profile?->identity_verified_at !== null,
            'mine' => $message->user_id === $viewer->id,
            'body' => $deleted ? null : $message->body,
            'deleted' => $deleted,
            'image' => ! $deleted && $message->attachment_path ? route('messenger.image', $message) : null,
            'image_name' => $deleted ? null : $message->attachment_name,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    // ───────────────────────── Liste et compteurs ─────────────────────────

    /** Requête des messages non lus de l'utilisateur (hors siens et supprimés). */
    private static function unreadQuery(User $user)
    {
        return MessengerMessage::query()
            ->join('messenger_participants as mp', function ($join) use ($user): void {
                $join->on('mp.conversation_id', '=', 'messenger_messages.conversation_id')->where('mp.user_id', $user->id)->whereNull('mp.left_at');
            })
            ->whereColumn('messenger_messages.id', '>', 'mp.last_read_message_id')
            ->whereNull('messenger_messages.deleted_at')
            ->where(fn ($query) => $query->whereNull('messenger_messages.user_id')->orWhere('messenger_messages.user_id', '!=', $user->id));
    }

    public static function unreadCount(User $user): int
    {
        return self::enabled() ? (int) self::unreadQuery($user)->count() : 0;
    }

    /**
     * Conversations du membre, la plus récente d'abord, avec aperçu et nombre de messages non lus.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function conversations(User $me): array
    {
        $conversations = MessengerConversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $me->id)->whereNull('left_at'))
            ->where(fn ($query) => $query->whereNotNull('last_message_id')->orWhere('created_by', $me->id))
            ->with(['participants' => fn ($query) => $query->whereNull('left_at'), 'participants.user.profile'])
            ->orderByDesc('last_message_at')->orderByDesc('id')->limit(100)->get();

        $unread = self::unreadQuery($me)->selectRaw('messenger_messages.conversation_id as cid, count(*) as total')->groupBy('messenger_messages.conversation_id')->pluck('total', 'cid');
        $last = MessengerMessage::query()->whereIn('id', $conversations->pluck('last_message_id')->filter())->get()->keyBy('id');

        return $conversations->map(function (MessengerConversation $conversation) use ($me, $unread, $last): array {
            $participants = $conversation->participants;
            $other = $conversation->isGroup() ? null : $participants->firstWhere('user_id', '!=', $me->id)?->user;
            $message = $last->get($conversation->last_message_id);

            $preview = match (true) {
                $message === null => 'Aucun message',
                $message->deleted_at !== null => 'Message supprimé',
                $message->body !== null && $message->body !== '' => mb_strimwidth($message->body, 0, 90, '…'),
                default => 'Photo',
            };

            return [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'title' => self::title($conversation, $me, $participants),
                'avatar' => $other?->profile?->photoUrl(),
                'verified' => $other?->profile?->identity_verified_at !== null,
                'preview' => ($message !== null && $message->user_id === $me->id ? 'Vous : ' : '').$preview,
                'last_at' => $conversation->last_message_at?->toIso8601String(),
                'unread' => (int) ($unread[$conversation->id] ?? 0),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, MessengerParticipant>  $participants
     */
    private static function title(MessengerConversation $conversation, User $me, Collection $participants): string
    {
        if ($conversation->isGroup()) {
            return $conversation->title ?: $participants->pluck('user.name')->filter()->implode(', ');
        }

        return (string) ($participants->firstWhere('user_id', '!=', $me->id)?->user?->name ?? 'Membre supprimé');
    }

    /**
     * Membres joignables dont le nom correspond à la recherche (aucune coordonnée renvoyée).
     *
     * @return array<int, array{id: int, name: string, avatar: ?string}>
     */
    public static function searchMembers(User $me, string $term): array
    {
        $words = array_slice(preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 4);

        if ($words === [] || mb_strlen(trim($term)) < 2) {
            return [];
        }

        $query = User::query()->with('profile')->where('is_active', true)->where('id', '!=', $me->id)
            ->where('email', 'not like', '%@legacy.invalid')
            ->whereNotIn('id', MessengerBlock::query()->where('blocker_id', $me->id)->select('blocked_id'))
            ->whereNotIn('id', MessengerBlock::query()->where('blocked_id', $me->id)->select('blocker_id'));

        foreach ($words as $word) {
            $query->where('name', 'like', '%'.addcslashes($word, '\\%_').'%');
        }

        return $query->orderBy('name')->limit(8)->get()
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'avatar' => $user->profile?->photoUrl(), 'verified' => $user->profile?->identity_verified_at !== null])->all();
    }
}
