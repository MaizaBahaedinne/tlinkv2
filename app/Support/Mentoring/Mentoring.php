<?php

namespace App\Support\Mentoring;

use App\Models\AppNotification;
use App\Models\Membership;
use App\Models\MentoringEvent;
use App\Models\MentoringMentor;
use App\Models\MentoringRex;
use App\Models\MentoringTicket;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\User;
use App\Support\AdminContext;
use App\Support\AuditLogger;
use App\Support\ModuleCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Mentorat : les membres remontent une demande, un incident ou un problème (personnel ou de leur collectif) ;
 * le manager mentor reçoit le ticket, le qualifie et l'affecte à un mentor, qui accompagne puis rédige le REX.
 *
 * Parcours : Reçue → Qualifiée → Affectée → En accompagnement → Résolue (REX obligatoire) → Clôturée,
 * ou « Non retenue » tant que le ticket n'est pas affecté.
 */
final class Mentoring
{
    public const MODULE = 'mentoring.tickets';

    public const MAX_TEXT = 5000;

    private static ?bool $ready = null;

    /** Le mentorat est ouvert : module actif et tables migrées. */
    public static function enabled(): bool
    {
        return self::tablesReady() && ModuleCatalog::isActive(self::MODULE);
    }

    private static function tablesReady(): bool
    {
        return self::$ready ??= Schema::hasTable('mentoring_tickets');
    }

    /**
     * Actions sur les tickets que l'administration ouvre à cet utilisateur : manager mentor de l'annuaire (tout),
     * ou actions cochées dans la matrice de son poste. Vide pour qui n'a aucun rôle de gestion.
     *
     * @return array<int, string>
     */
    public static function adminActions(User $user): array
    {
        if (! self::tablesReady()) {
            return [];
        }

        $actions = self::matrixActions($user);

        if (MentoringMentor::query()->where('user_id', $user->id)->where('is_active', true)->where('is_manager', true)->exists()) {
            $actions = array_merge($actions, ['read', 'update']);
        }

        return array_values(array_unique($actions));
    }

    // ───────────────────────── Rôles et droits ─────────────────────────

    public static function mentorRow(User $user): ?MentoringMentor
    {
        return MentoringMentor::query()->where('user_id', $user->id)->where('is_active', true)->first();
    }

    public static function isMentor(User $user): bool
    {
        return self::mentorRow($user) !== null;
    }

    /** Manager mentor : inscrit comme tel dans l'annuaire, ou poste dont la matrice de droits autorise « modifier » sur les tickets. */
    public static function isManager(User $user): bool
    {
        return MentoringMentor::query()->where('user_id', $user->id)->where('is_active', true)->where('is_manager', true)->exists()
            || in_array('update', self::matrixActions($user), true);
    }

    /** Lecture seule : poste dont la matrice autorise « consulter » les tickets, sans les gérer. */
    public static function isObserver(User $user): bool
    {
        return ! self::isManager($user) && in_array('read', self::matrixActions($user), true);
    }

    /** Consulter la liste de tous les tickets (manager mentor ou lecture seule). */
    public static function canSeeAll(User $user): bool
    {
        return self::isManager($user) || self::isObserver($user);
    }

    /**
     * Actions accordées sur les tickets par la matrice des postes occupés (périmètre sans objet : le mentorat est national).
     *
     * @return array<int, string>
     */
    private static function matrixActions(User $user): array
    {
        $actions = [];

        foreach (self::matrixAssignments()->whereHas('person', fn ($query) => $query->where('user_id', $user->id))->get() as $assignment) {
            $actions = array_merge($actions, self::nodeActions($assignment));
        }

        return array_values(array_unique($actions));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<OrgAssignment> */
    private static function matrixAssignments()
    {
        $today = now()->toDateString();

        return OrgAssignment::query()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->whereHas('node', fn ($query) => $query->where('kind', 'position')->whereNotNull('module_permissions'))
            ->with('node');
    }

    /** @return array<int, string> */
    private static function nodeActions(OrgAssignment $assignment): array
    {
        $matrix = (array) $assignment->node?->module_permissions;

        return array_map('strval', (array) ($matrix[self::MODULE] ?? []));
    }

    /** Gérer l'annuaire des mentors : manager mentor, ou administrateur de toute la plateforme (qui ne lit pas pour autant les tickets). */
    public static function canAdminister(User $user): bool
    {
        return self::isManager($user) || (new AdminContext($user))->scope('entity.manage') === [];
    }

    /** Équipe de mentors du ticket : mentor affecté ou manager mentor, jamais sur sa propre demande. */
    public static function isStaff(MentoringTicket $ticket, User $user): bool
    {
        if ((int) $ticket->requester_id === (int) $user->id) {
            return false;
        }

        return ($ticket->mentor_id !== null && (int) $ticket->mentor_id === (int) $user->id) || self::isManager($user);
    }

    /** Qualifier, affecter, écarter, clôturer, rouvrir : réservé au manager mentor (hors sa propre demande). */
    public static function isManagerOf(MentoringTicket $ticket, User $user): bool
    {
        return (int) $ticket->requester_id !== (int) $user->id && self::isManager($user);
    }

    public static function canView(MentoringTicket $ticket, User $user): bool
    {
        return (int) $ticket->requester_id === (int) $user->id || self::isStaff($ticket, $user);
    }

    /** Ids des managers mentors actifs. @return array<int, int> */
    public static function managerIds(): array
    {
        $ids = MentoringMentor::query()->where('is_manager', true)->where('is_active', true)->pluck('user_id')->map(fn ($id): int => (int) $id)->all();

        foreach (self::matrixAssignments()->whereHas('person', fn ($query) => $query->whereNotNull('user_id'))->with('person:id,user_id')->get() as $assignment) {
            if (in_array('update', self::nodeActions($assignment), true)) {
                $ids[] = (int) $assignment->person->user_id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** Mentors disponibles pour une affectation. @return Collection<int, MentoringMentor> */
    public static function mentors(): Collection
    {
        return MentoringMentor::query()->with('user')->where('is_active', true)
            ->get()->sortBy(fn (MentoringMentor $row): string => mb_strtolower((string) $row->user?->name))->values();
    }

    /** Nombre de tickets en cours confiés à chaque mentor. @return array<int, int> */
    public static function workload(): array
    {
        return MentoringTicket::query()->whereIn('status', ['assigned', 'in_progress'])->whereNotNull('mentor_id')
            ->select('mentor_id', DB::raw('count(*) as total'))->groupBy('mentor_id')->pluck('total', 'mentor_id')
            ->map(fn ($total): int => (int) $total)->all();
    }

    // ───────────────────────── Collectifs du membre ─────────────────────────

    /**
     * Clubs du membre : adhésions en cours et postes occupés.
     *
     * @return Collection<int, OrgNode>
     */
    public static function clubsOf(User $user): Collection
    {
        $today = now()->toDateString();

        $fromMemberships = Membership::query()->where('user_id', $user->id)->where('status', 'approved')->whereNotNull('node_id')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with('node')->get()->map(fn (Membership $membership): ?OrgNode => self::clubOf($membership->node));

        $fromPositions = OrgAssignment::query()->whereHas('person', fn ($query) => $query->where('user_id', $user->id))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with('node')->get()->map(fn (OrgAssignment $assignment): ?OrgNode => self::clubOf($assignment->node));

        return $fromMemberships->concat($fromPositions)->filter()->unique('id')->sortBy(fn (OrgNode $node): string => mb_strtolower((string) $node->title))->values();
    }

    private static function clubOf(?OrgNode $node): ?OrgNode
    {
        for ($guard = 0; $guard < 20 && $node !== null; $guard++) {
            if ($node->kind === 'club') {
                return $node;
            }

            $node = $node->parent_id !== null ? OrgNode::query()->find($node->parent_id) : null;
        }

        return null;
    }

    // ───────────────────────── Parcours du ticket ─────────────────────────

    /**
     * Le membre remonte un ticket : les managers mentors sont prévenus.
     *
     * @param array{type: string, audience: string, title: string, description: string, collective_type?: ?string, collective_name?: ?string, node_id?: ?int} $data
     */
    public static function submit(User $user, array $data): MentoringTicket
    {
        $collective = $data['audience'] === 'collective';
        $node = null;

        if ($collective && ! empty($data['node_id'])) {
            $node = self::clubsOf($user)->firstWhere('id', (int) $data['node_id']);

            if ($node === null) {
                throw ValidationException::withMessages(['node_id' => 'Ce club ne fait pas partie de vos collectifs.']);
            }
        }

        $ticket = DB::transaction(function () use ($user, $data, $collective, $node): MentoringTicket {
            $ticket = MentoringTicket::query()->create([
                'requester_id' => $user->id,
                'type' => $data['type'],
                'audience' => $data['audience'],
                'collective_type' => $collective ? ($node !== null ? 'club' : ($data['collective_type'] ?? 'other')) : null,
                'collective_name' => $collective ? ($node?->title ?? trim((string) ($data['collective_name'] ?? '')) ?: null) : null,
                'node_id' => $node?->id,
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'status' => 'new',
                'priority' => 'normal',
            ]);

            $ticket->update(['reference' => 'MT-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT)]);
            self::log($ticket, $user, 'status', 'Demande transmise aux managers mentors.');

            return $ticket;
        });

        self::notify(self::managerIds(), $ticket, 'Nouveau ticket de mentorat', $user->name.' a remonté « '.$ticket->title.' ».', $user->id);

        return $ticket;
    }

    /** Qualification par le manager mentor : catégorie, priorité et analyse (note interne). */
    public static function qualify(MentoringTicket $ticket, User $actor, string $category, string $priority, ?string $note): void
    {
        if (! in_array($ticket->status, ['new', 'qualified', 'assigned'], true)) {
            self::refuse('Ce ticket ne peut plus être requalifié.');
        }

        $ticket->update([
            'category' => $category,
            'priority' => $priority,
            'qualification_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'qualified_by' => $actor->id,
            'qualified_at' => now(),
            'status' => $ticket->status === 'new' ? 'qualified' : $ticket->status,
        ]);

        $summary = 'Qualifiée : '.MentoringTicket::CATEGORIES[$category].' · priorité '.mb_strtolower(MentoringTicket::PRIORITIES[$priority]).'.';
        self::log($ticket, $actor, 'qualification', $summary);
        self::notify([$ticket->requester_id], $ticket, 'Votre demande a été qualifiée', $ticket->reference.' · '.$summary, $actor->id);
        self::audit($actor, 'mentoring.ticket.qualified', $ticket, ['category' => $category, 'priority' => $priority]);
    }

    /** Un ticket hors périmètre n'est pas retenu, avec un motif communiqué au demandeur. */
    public static function reject(MentoringTicket $ticket, User $actor, string $reason): void
    {
        if (! in_array($ticket->status, ['new', 'qualified'], true)) {
            self::refuse('Seul un ticket non affecté peut être écarté.');
        }

        $ticket->update(['status' => 'rejected', 'closed_at' => now()]);

        self::log($ticket, $actor, 'status', 'Demande non retenue : '.trim($reason));
        self::notify([$ticket->requester_id], $ticket, 'Votre demande n’a pas été retenue', $ticket->reference.' · '.trim($reason), $actor->id);
        self::audit($actor, 'mentoring.ticket.rejected', $ticket, ['reason' => trim($reason)]);
    }

    /** Affectation (ou réaffectation) à un mentor actif ; le ticket doit avoir été qualifié. */
    public static function assign(MentoringTicket $ticket, User $actor, int $mentorUserId): void
    {
        if (! in_array($ticket->status, ['qualified', 'assigned', 'in_progress'], true) || $ticket->category === null) {
            self::refuse('Qualifiez le ticket avant de l’affecter à un mentor.');
        }

        $row = MentoringMentor::query()->with('user')->where('user_id', $mentorUserId)->where('is_active', true)->first();

        if ($row === null || $row->user === null) {
            self::refuse('Ce membre n’est pas un mentor actif.');
        }

        if ((int) $row->user_id === (int) $ticket->requester_id) {
            self::refuse('Un mentor ne peut pas accompagner sa propre demande.');
        }

        $previous = $ticket->mentor_id !== null ? (int) $ticket->mentor_id : null;

        $ticket->update([
            'mentor_id' => $row->user_id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'status' => $ticket->status === 'qualified' ? 'assigned' : $ticket->status,
        ]);

        self::log($ticket, $actor, 'assignment', 'Affectée à '.$row->user->name.'.', ['mentor_id' => $row->user_id]);
        self::notify([$row->user_id], $ticket, 'Nouvel accompagnement à suivre', $ticket->reference.' · « '.$ticket->title.' » vous est confié.', $actor->id);
        self::notify([$ticket->requester_id], $ticket, 'Un mentor vous accompagne', $ticket->reference.' · '.$row->user->name.' suit votre demande.', $actor->id);

        if ($previous !== null && $previous !== (int) $row->user_id) {
            self::notify([$previous], $ticket, 'Accompagnement réaffecté', $ticket->reference.' · le ticket est désormais confié à '.$row->user->name.'.', $actor->id);
        }

        self::audit($actor, 'mentoring.ticket.assigned', $ticket, ['mentor_id' => $row->user_id]);
    }

    public static function start(MentoringTicket $ticket, User $actor): void
    {
        if ($ticket->status !== 'assigned') {
            self::refuse('L’accompagnement ne peut démarrer que sur un ticket affecté.');
        }

        $ticket->update(['status' => 'in_progress', 'started_at' => now()]);

        self::log($ticket, $actor, 'status', 'Accompagnement démarré.');
        self::notify([$ticket->requester_id], $ticket, 'Votre accompagnement démarre', $ticket->reference.' · votre mentor a pris en charge votre demande.', $actor->id);
    }

    /** Message du fil : public (visible du demandeur) ou note interne (équipe de mentors). */
    public static function comment(MentoringTicket $ticket, User $actor, string $body, bool $internal): MentoringEvent
    {
        if (! in_array($ticket->status, ['new', 'qualified', 'assigned', 'in_progress', 'resolved'], true)) {
            self::refuse('Ce ticket est clos : le fil de discussion est fermé.');
        }

        $staff = self::isStaff($ticket, $actor);

        if ($internal && ! $staff) {
            self::refuse('Seule l’équipe de mentors peut écrire une note interne.');
        }

        $event = self::log($ticket, $actor, $internal ? 'internal' : 'comment', trim($body));

        if ($internal) {
            self::notify([$ticket->mentor_id], $ticket, 'Note interne sur un ticket', $ticket->reference.' · '.$actor->name.' a ajouté une note.', $actor->id);
        } elseif ($staff) {
            self::notify([$ticket->requester_id], $ticket, 'Nouveau message de votre mentor', $ticket->reference.' · '.$actor->name.' vous a répondu.', $actor->id);
        } else {
            self::notify($ticket->mentor_id !== null ? [$ticket->mentor_id] : self::managerIds(), $ticket, 'Nouveau message du demandeur', $ticket->reference.' · '.$actor->name.' a écrit sur « '.$ticket->title.' ».', $actor->id);
        }

        return $event;
    }

    /**
     * Retour d'expérience : contexte, actions menées, résultat, enseignements et recommandations.
     *
     * @param array{context: string, actions: string, outcome: string, lessons: string, recommendations?: ?string} $data
     */
    public static function saveRex(MentoringTicket $ticket, User $actor, array $data): MentoringRex
    {
        if (! in_array($ticket->status, ['in_progress', 'resolved'], true)) {
            self::refuse('Le REX se rédige pendant l’accompagnement ou avant la clôture.');
        }

        $rex = MentoringRex::query()->firstOrNew(['ticket_id' => $ticket->id]);
        $created = ! $rex->exists;

        $rex->fill([
            'author_id' => $actor->id,
            'context' => trim($data['context']),
            'actions' => trim($data['actions']),
            'outcome' => trim($data['outcome']),
            'lessons' => trim($data['lessons']),
            'recommendations' => isset($data['recommendations']) && trim((string) $data['recommendations']) !== '' ? trim($data['recommendations']) : null,
        ])->save();

        self::log($ticket, $actor, 'rex', $created ? 'REX rédigé.' : 'REX mis à jour.');
        self::audit($actor, 'mentoring.rex.saved', $ticket, []);

        return $rex;
    }

    /** Fin de l'accompagnement : le REX est obligatoire. */
    public static function resolve(MentoringTicket $ticket, User $actor): void
    {
        if ($ticket->status !== 'in_progress') {
            self::refuse('Seul un accompagnement en cours peut être résolu.');
        }

        if (! MentoringRex::query()->where('ticket_id', $ticket->id)->exists()) {
            self::refuse('Rédigez le REX avant de déclarer le ticket résolu.');
        }

        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        self::log($ticket, $actor, 'status', 'Accompagnement terminé : demande résolue.');
        self::notify([$ticket->requester_id, ...self::managerIds()], $ticket, 'Demande résolue', $ticket->reference.' · l’accompagnement de « '.$ticket->title.' » est terminé.', $actor->id);
        self::audit($actor, 'mentoring.ticket.resolved', $ticket, []);
    }

    public static function close(MentoringTicket $ticket, User $actor): void
    {
        if ($ticket->status !== 'resolved') {
            self::refuse('Seul un ticket résolu peut être clôturé.');
        }

        $ticket->update(['status' => 'closed', 'closed_at' => now()]);

        self::log($ticket, $actor, 'status', 'Ticket clôturé.');
        self::audit($actor, 'mentoring.ticket.closed', $ticket, []);
    }

    public static function reopen(MentoringTicket $ticket, User $actor): void
    {
        if (! in_array($ticket->status, ['resolved', 'closed'], true) || $ticket->mentor_id === null) {
            self::refuse('Ce ticket ne peut pas être rouvert.');
        }

        $ticket->update(['status' => 'in_progress', 'resolved_at' => null, 'closed_at' => null]);

        self::log($ticket, $actor, 'status', 'Ticket rouvert : l’accompagnement reprend.');
        self::notify([$ticket->requester_id, $ticket->mentor_id], $ticket, 'Ticket rouvert', $ticket->reference.' · l’accompagnement reprend.', $actor->id);
        self::audit($actor, 'mentoring.ticket.reopened', $ticket, []);
    }

    // ───────────────────────── Annuaire des mentors ─────────────────────────

    public static function saveMentor(User $actor, int $userId, bool $manager, ?string $specialties): MentoringMentor
    {
        $user = User::query()->where('id', $userId)->where('is_active', true)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['member' => 'Ce membre est introuvable ou inactif.']);
        }

        $row = MentoringMentor::query()->firstOrNew(['user_id' => $user->id]);
        $row->fill([
            'is_manager' => $manager,
            'is_active' => true,
            'specialties' => $specialties !== null && trim($specialties) !== '' ? trim($specialties) : null,
        ]);

        if (! $row->exists) {
            $row->created_by = $actor->id;
        }

        $row->save();
        self::audit($actor, 'mentoring.mentor.saved', null, ['user_id' => $user->id, 'manager' => $manager]);

        return $row;
    }

    public static function updateMentor(MentoringMentor $row, User $actor, bool $manager, bool $active, ?string $specialties): void
    {
        if (! $active) {
            self::assertNoOpenTickets($row);
        }

        $row->update([
            'is_manager' => $manager,
            'is_active' => $active,
            'specialties' => $specialties !== null && trim($specialties) !== '' ? trim($specialties) : null,
        ]);

        self::audit($actor, 'mentoring.mentor.saved', null, ['user_id' => $row->user_id, 'manager' => $manager, 'active' => $active]);
    }

    public static function removeMentor(MentoringMentor $row, User $actor): void
    {
        self::assertNoOpenTickets($row);
        $row->delete();
        self::audit($actor, 'mentoring.mentor.removed', null, ['user_id' => $row->user_id]);
    }

    private static function assertNoOpenTickets(MentoringMentor $row): void
    {
        $open = MentoringTicket::query()->where('mentor_id', $row->user_id)->whereIn('status', ['assigned', 'in_progress'])->count();

        if ($open > 0) {
            throw ValidationException::withMessages(['mentor' => 'Réaffectez d’abord les '.$open.' ticket(s) en cours de ce mentor.']);
        }
    }

    /**
     * Membres actifs, absents de l'annuaire, dont le nom correspond à la recherche.
     *
     * @return Collection<int, User>
     */
    public static function searchMembers(string $term): Collection
    {
        $words = array_slice(preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 4);

        if ($words === [] || mb_strlen(trim($term)) < 2) {
            return collect();
        }

        $query = User::query()->where('is_active', true)->where('email', 'not like', '%@legacy.invalid')
            ->whereNotIn('id', MentoringMentor::query()->select('user_id'));

        foreach ($words as $word) {
            $query->where('name', 'like', '%'.addcslashes($word, '\\%_').'%');
        }

        return $query->orderBy('name')->limit(10)->get(['id', 'name']);
    }

    // ───────────────────────── Aides internes ─────────────────────────

    private static function log(MentoringTicket $ticket, ?User $actor, string $kind, ?string $body, array $meta = []): MentoringEvent
    {
        return MentoringEvent::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor?->id,
            'kind' => $kind,
            'body' => $body,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }

    /**
     * @param array<int, int|null> $userIds
     */
    private static function notify(array $userIds, MentoringTicket $ticket, string $title, string $body, ?int $exceptId): void
    {
        foreach (array_unique(array_filter($userIds)) as $id) {
            if ($exceptId !== null && (int) $id === $exceptId) {
                continue;
            }

            AppNotification::query()->create([
                'user_id' => $id,
                'type' => 'mentoring.ticket',
                'title' => $title,
                'body' => $body,
                'data' => ['ticket_id' => $ticket->id, 'url' => route('mentoring.show', $ticket, false)],
            ]);
        }
    }

    private static function audit(User $actor, string $action, ?MentoringTicket $ticket, array $metadata): void
    {
        AuditLogger::record($actor->id, $action, $ticket !== null ? MentoringTicket::class : User::class, $ticket?->id ?? ($metadata['user_id'] ?? null), $metadata, request()?->ip());
    }

    private static function refuse(string $message): never
    {
        throw ValidationException::withMessages(['ticket' => $message]);
    }
}
