<?php

namespace App\Http\Controllers;

use App\Models\MessengerConversation;
use App\Models\MessengerMessage;
use App\Models\User;
use App\Support\Messenger\Messenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T.Link Messenger : page de messagerie et points d'accès JSON interrogés par le navigateur pour le temps réel.
 */
class MessengerController extends Controller
{
    public function index(Request $request, ?MessengerConversation $conversation = null): View
    {
        $this->open();

        if ($conversation !== null) {
            $this->authorizeParticipant($request, $conversation);
        }

        return view('messenger.index', ['activeId' => $conversation?->id]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $this->open();

        return response()->json(['conversations' => Messenger::conversations($request->user()), 'unread' => Messenger::unreadCount($request->user())]);
    }

    public function unread(Request $request): JsonResponse
    {
        return response()->json(['unread' => Messenger::enabled() ? Messenger::unreadCount($request->user()) : 0]);
    }

    public function members(Request $request): JsonResponse
    {
        $this->open();
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);

        return response()->json(['members' => Messenger::searchMembers($request->user(), (string) ($data['q'] ?? ''))]);
    }

    public function direct(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->open();
        $conversation = Messenger::direct($request->user(), $user);

        if ($request->expectsJson()) {
            return $conversation === null
                ? response()->json(['message' => 'Vous ne pouvez pas écrire à ce membre.'], 422)
                : response()->json(['conversation' => $conversation->id]);
        }

        return $conversation === null
            ? redirect()->route('messenger.index')->with('status', 'Vous ne pouvez pas écrire à ce membre.')
            : redirect()->route('messenger.show', $conversation);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $this->open();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'members' => ['required', 'array', 'min:1', 'max:'.(Messenger::MAX_GROUP_MEMBERS - 1)],
            'members.*' => ['integer'],
        ], ['members.required' => 'Choisissez au moins un membre à ajouter au groupe.']);

        $conversation = Messenger::createGroup($request->user(), trim($data['title']), $data['members']);

        return redirect()->route('messenger.show', $conversation);
    }

    public function messages(Request $request, MessengerConversation $conversation): JsonResponse
    {
        $this->open();
        $this->authorizeParticipant($request, $conversation);

        $data = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'before' => ['nullable', 'integer', 'min:0'],
            'since' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json(Messenger::poll($conversation, $request->user(), (int) ($data['after'] ?? 0), (int) ($data['before'] ?? 0), isset($data['since']) ? (int) $data['since'] : null));
    }

    public function send(Request $request, MessengerConversation $conversation): JsonResponse
    {
        $this->open();
        $this->authorizeParticipant($request, $conversation);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:'.Messenger::MAX_BODY],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'body.max' => 'Message trop long ('.Messenger::MAX_BODY.' caractères maximum).',
            'image.max' => 'Image trop lourde (5 Mo maximum).',
            'image.image' => 'Le fichier joint doit être une image (jpg, png ou webp).',
            'image.mimes' => 'Le fichier joint doit être une image (jpg, png ou webp).',
        ]);

        if ($reason = Messenger::restriction($conversation, $request->user())) {
            return response()->json(['message' => $reason, 'errors' => ['body' => [$reason]]], 422);
        }

        $message = Messenger::send($conversation, $request->user(), $data['body'] ?? null, $request->file('image'));
        $message->load('author.profile');

        return response()->json(['message' => Messenger::present($message, $request->user())], 201);
    }

    public function read(Request $request, MessengerConversation $conversation): JsonResponse
    {
        $this->open();
        $this->authorizeParticipant($request, $conversation);
        $data = $request->validate(['up_to' => ['required', 'integer', 'min:1']]);

        Messenger::markRead($conversation, $request->user(), (int) $data['up_to']);

        return response()->json(['unread' => Messenger::unreadCount($request->user())]);
    }

    public function typing(Request $request, MessengerConversation $conversation): JsonResponse
    {
        $this->open();
        $this->authorizeParticipant($request, $conversation);

        Messenger::typing($conversation, $request->user());

        return response()->json(['ok' => true]);
    }

    public function destroyMessage(Request $request, MessengerMessage $message): JsonResponse
    {
        $this->open();
        abort_unless($message->user_id === $request->user()->id, 403);
        $this->authorizeParticipant($request, $message->conversation);

        Messenger::deleteMessage($message);

        return response()->json(['ok' => true]);
    }

    /** Image jointe : réservée aux participants de la conversation. */
    public function image(Request $request, MessengerMessage $message): StreamedResponse
    {
        $this->open();
        $this->authorizeParticipant($request, $message->conversation);
        abort_unless($message->attachment_path && Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name, [
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function leave(Request $request, MessengerConversation $conversation): RedirectResponse
    {
        $this->open();
        $this->authorizeParticipant($request, $conversation);
        abort_unless($conversation->isGroup(), 422);

        Messenger::leave($conversation, $request->user());

        return redirect()->route('messenger.index')->with('status', 'Vous avez quitté le groupe.');
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        $this->open();
        Messenger::block($request->user(), $user);

        return back()->with('status', 'Membre bloqué : il ne peut plus vous écrire.');
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        $this->open();
        Messenger::unblock($request->user(), $user);

        return back()->with('status', 'Membre débloqué.');
    }

    private function open(): void
    {
        abort_unless(Messenger::enabled(), 404);
    }

    private function authorizeParticipant(Request $request, MessengerConversation $conversation): void
    {
        abort_unless(Messenger::participant($conversation, $request->user()) !== null, 403, 'Vous ne participez pas à cette conversation.');
    }
}
