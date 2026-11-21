<?php

namespace App\Http\Controllers;

use App\Models\EventPayment;
use App\Models\EventBusStop;
use App\Models\EventRegistrationImport;
use App\Models\EventTransportBooking;
use App\Models\FoundationEvent;
use App\Models\Mandate;
use App\Models\Profile;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Events\EventPayments;
use App\Support\Events\EventRegistrar;
use App\Support\Events\EventRegistrationException;
use App\Support\Events\EventTransport;
use App\Support\MembershipRequests;
use App\Support\OrgScopes;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Page publique où un inscrit importé (nom, prénom, téléphone) retrouve sa fiche avec son numéro
 * et la complète : photo, identité, rôle et parcours dans la Fondation, compte, preuve de paiement.
 */
class EventCompletionController extends Controller
{
    private const SESSION_KEY = 'event_completion';

    public function show(Request $request, FoundationEvent $event): View
    {
        $this->assertAvailable($event);
        $entry = $this->sessionEntry($request, $event);

        if ($entry === null) {
            return view('event-completion', ['event' => $event, 'entry' => null, 'done' => (bool) session('done')]);
        }

        $release = $this->releaseFor($event, $entry);
        $methods = $this->depositMethods($event);
        $amount = $release !== null ? (float) $release->price : (float) ($event->price ?? 0);

        return view('event-completion', [
            'event' => $event,
            'entry' => $entry,
            'done' => false,
            'release' => $release,
            'amount' => $amount,
            'methods' => $methods,
            'methodLabels' => EventPayment::METHODS,
            'scopes' => OrgScopes::all(),
            'clubs' => MembershipRequests::clubsByScope(true),
            'mandates' => Mandate::query()->where('status', Mandate::STATUS_CLOSED)->orderByDesc('start_year')->orderByDesc('id')->get(),
            'account' => $request->user(),
            'transport' => $this->transportData($event),
        ]);
    }

    /** Retrouve la fiche par le numéro de téléphone (8 derniers chiffres) et l'ouvre pour cette session. */
    public function lookup(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->assertAvailable($event);

        $data = $request->validate(['phone' => ['required', 'string', 'max:30']], ['phone.required' => 'Saisissez votre numéro de téléphone.']);
        $key = EventRegistrationImport::phoneKey($data['phone']);

        $entry = $key === null ? null : EventRegistrationImport::query()->where('event_id', $event->id)->where('phone_key', $key)->first();

        if ($entry === null) {
            throw ValidationException::withMessages(['phone' => 'Aucune inscription trouvée avec ce numéro. Vérifiez-le ou contactez l’organisation.']);
        }

        if ($entry->isCompleted()) {
            throw ValidationException::withMessages(['phone' => 'Cette fiche est déjà complétée. Connectez-vous avec votre compte pour suivre votre inscription.']);
        }

        $request->session()->put(self::SESSION_KEY.'.'.$event->id, $entry->id);

        return redirect()->route('event.complete', $event);
    }

    /** Quitte la fiche ouverte (mauvais numéro). */
    public function forget(Request $request, FoundationEvent $event): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY.'.'.$event->id);

        return redirect()->route('event.complete', $event);
    }

    /** Postes d'une partie ou d'un club (liste déroulante de la fiche). */
    public function positions(Request $request, FoundationEvent $event): JsonResponse
    {
        $this->assertAvailable($event);
        $data = $request->validate(['target' => ['required', 'string', 'max:80']]);
        [$scope, $clubId] = $this->parseTarget($data['target'], 'target');

        return response()->json(['positions' => MembershipRequests::memberPositions($scope, $clubId)
            ->map(fn ($node) => ['id' => $node->id, 'title' => $node->title, 'subtitle' => $node->subtitle, 'leader' => MembershipRequests::isLeaderPosition($node)])
            ->values()]);
    }

    public function store(Request $request, FoundationEvent $event, EventRegistrar $registrar, EventPayments $payments, EventTransport $transportService): RedirectResponse
    {
        $this->assertAvailable($event);
        $entry = $this->sessionEntry($request, $event);

        if ($entry === null) {
            return redirect()->route('event.complete', $event)->with('error', 'Votre session a expiré : saisissez de nouveau votre numéro de téléphone.');
        }

        $account = $request->user();
        $release = $this->releaseFor($event, $entry);
        $methods = $this->depositMethods($event);
        $amount = $release !== null ? (float) $release->price : (float) ($event->price ?? 0);
        $payable = $amount > 0 && $methods !== [];
        $proofLater = $request->boolean('proof_later') && ! $request->hasFile('proof_camera') && ! $request->hasFile('proof_file');
        $transport = $this->transportData($event);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birth_date' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'avatar' => [$account?->profile?->photo_path ? 'nullable' : 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'email' => $account ? ['nullable'] : ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => $account ? ['nullable'] : ['required', 'confirmed', Password::defaults()],
            'scope' => ['nullable', Rule::in(OrgScopes::slugs())],
            'club_id' => ['nullable', 'integer'],
            'position_id' => ['nullable', 'integer'],
            'joined_mandate_id' => ['nullable', 'integer', Rule::exists('mandates', 'id')->where('status', Mandate::STATUS_CLOSED)],
            'joined_scope' => ['nullable', 'required_with:joined_mandate_id', Rule::in(OrgScopes::slugs())],
            'joined_club_id' => ['nullable', 'integer'],
            'joined_position_id' => ['nullable', 'integer'],
            'method' => [$payable && ! $proofLater ? 'required' : 'nullable', Rule::in(array_keys($methods))],
            'reference' => ['nullable', 'string', 'max:255'],
            'proof_camera' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:6144'],
            'region_id' => [$transport !== null ? 'required' : 'nullable', 'integer'],
            'mode' => [$transport !== null ? 'required' : 'nullable', Rule::in(array_keys(EventTransportBooking::MODES))],
            'stop_id' => ['nullable', 'integer', 'required_if:mode,bus'],
        ], [
            'avatar.required' => 'Une photo de profil est obligatoire.',
            'email.unique' => 'Un compte existe déjà avec cet e-mail : connectez-vous, puis rouvrez ce lien.',
            'joined_scope.required_with' => 'Choisissez la partie rejointe à votre arrivée.',
            'method.required' => 'Choisissez le moyen de paiement utilisé.',
            'region_id.required' => 'Choisissez votre région de départ.',
            'mode.required' => 'Indiquez comment vous viendrez (bus ou véhicule personnel).',
            'stop_id.required_if' => 'Choisissez votre bus et votre point de départ.',
        ]);

        $proof = $request->file('proof_camera') ?? $request->file('proof_file');

        if ($payable && ! $proofLater && $proof === null) {
            throw ValidationException::withMessages(['proof_file' => 'Ajoutez la preuve de paiement (photo prise avec la caméra ou fichier), ou cochez « Je la déposerai plus tard ».']);
        }

        [$scope, $clubId] = $this->resolveTarget($data['scope'] ?? null, $data['club_id'] ?? null, 'club_id');
        $position = $this->resolvePosition($scope, $clubId, $data['position_id'] ?? null, 'position_id');

        $joinedMandate = isset($data['joined_mandate_id']) ? Mandate::query()->find($data['joined_mandate_id']) : null;
        $joinedScope = null;
        $joinedClubId = null;
        $joinedPosition = null;

        if ($joinedMandate !== null) {
            [$joinedScope, $joinedClubId] = $this->resolveTarget($data['joined_scope'] ?? null, $data['joined_club_id'] ?? null, 'joined_club_id');
            $joinedPosition = $this->resolvePosition($joinedScope, $joinedClubId, $data['joined_position_id'] ?? null, 'joined_position_id');
        }

        $avatarPath = $request->hasFile('avatar') ? $request->file('avatar')->store('profiles/avatars', 'public') : null;
        $created = false;

        try {
            $user = DB::transaction(function () use ($request, $event, $entry, $data, $account, $release, $avatarPath, $registrar, $payments, $transportService, $transport, $payable, $proof, $scope, $clubId, $position, $joinedMandate, $joinedScope, $joinedClubId, $joinedPosition, &$created): User {
                $locked = EventRegistrationImport::query()->whereKey($entry->id)->lockForUpdate()->first();

                if ($locked === null || $locked->isCompleted()) {
                    throw ValidationException::withMessages(['phone' => 'Cette fiche est déjà complétée.']);
                }

                $user = $account;

                if ($user === null) {
                    $user = User::query()->create([
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'email' => $data['email'],
                        'password' => Hash::make($data['password']),
                    ]);
                    $created = true;
                }

                $profile = Profile::query()->firstOrNew(['user_id' => $user->id]);
                $profile->fill([
                    'phone' => $profile->phone ?: $locked->phone,
                    'gender' => $data['gender'],
                    'birth_date' => $data['birth_date'],
                    'photo_path' => $avatarPath ?? $profile->photo_path,
                ])->save();

                $registration = $registrar->registerImported($event, $user, $release);

                if ($payable && $proof !== null) {
                    try {
                        $payments->submit($registration, $user, $data['method'], EventPayments::outstanding($registration), $data['reference'] ?? null, $proof);
                    } catch (EventRegistrationException $exception) {
                        throw ValidationException::withMessages(['method' => $exception->getMessage()]);
                    }
                }

                if ($transport !== null) {
                    try {
                        $booking = $transportService->book($registration, (int) $data['region_id'], $data['mode'], isset($data['stop_id']) ? (int) $data['stop_id'] : null);
                    } catch (EventRegistrationException $exception) {
                        throw ValidationException::withMessages([$data['mode'] === 'bus' ? 'stop_id' : 'region_id' => $exception->getMessage()]);
                    }

                    $booking->loadMissing(['region', 'bus', 'stop.point']);
                }

                if ($joinedMandate !== null) {
                    try {
                        MembershipRequests::declarePast($user, $joinedMandate, (string) $joinedScope, $joinedClubId, $joinedPosition?->id, $request->ip());
                    } catch (ValidationException $exception) {
                        throw ValidationException::withMessages(['joined_mandate_id' => collect($exception->errors())->flatten()->first()]);
                    }

                    $user->forceFill(['joined_mandate_id' => $joinedMandate->id, 'member_origin' => 'alumni', 'history_onboarding_at' => now()])->save();
                }

                $locked->update([
                    'status' => EventRegistrationImport::STATUS_COMPLETED,
                    'user_id' => $user->id,
                    'registration_id' => $registration->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'completed_at' => now(),
                    'declared' => [
                        'scope' => $scope !== null ? OrgScopes::label($scope) : null,
                        'club' => $clubId !== null ? $this->clubTitle($clubId) : null,
                        'position' => $position?->title,
                        'transport' => isset($booking) ? [
                            'region' => $booking->region?->name,
                            'mode' => EventTransportBooking::MODES[$booking->mode] ?? $booking->mode,
                            'bus' => $booking->mode === 'bus' ? trim($booking->bus?->name.' · '.$booking->stop?->point?->label(), ' ·') : null,
                        ] : null,
                        'joined' => $joinedMandate === null ? null : [
                            'mandate' => $joinedMandate->label,
                            'scope' => OrgScopes::label((string) $joinedScope),
                            'club' => $joinedClubId !== null ? $this->clubTitle($joinedClubId) : null,
                            'position' => $joinedPosition?->title,
                        ],
                    ],
                ]);

                AuditLogger::record($user->id, 'event.registration.completed', EventRegistrationImport::class, $locked->id, ['event_id' => $event->id, 'account_created' => $created], $request->ip());

                return $user;
            });
        } catch (\Throwable $exception) {
            if ($avatarPath !== null) {
                Storage::disk('public')->delete($avatarPath);
            }

            throw $exception;
        }

        $request->session()->forget(self::SESSION_KEY.'.'.$event->id);

        if ($created) {
            event(new Registered($user));
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('event.complete', $event)->with('done', true);
    }

    /**
     * Régions de départ et bus proposés par l'événement (null s'il n'organise pas de transport).
     *
     * @return array{regions: \Illuminate\Support\Collection, stops: \Illuminate\Support\Collection}|null
     */
    private function transportData(FoundationEvent $event): ?array
    {
        $regions = $event->transportRegions()->get();

        if ($regions->isEmpty()) {
            return null;
        }

        $stops = EventBusStop::query()
            ->whereHas('bus', fn ($query) => $query->where('event_id', $event->id))
            ->with(['bus', 'point'])
            ->orderBy('departs_at')
            ->get()
            ->each(fn (EventBusStop $stop) => $stop->bus->setAttribute('remaining_seats', $stop->bus->remaining()));

        return ['regions' => $regions, 'stops' => $stops];
    }

    private function assertAvailable(FoundationEvent $event): void
    {
        abort_if(in_array($event->status, ['draft', 'cancelled'], true), 404);
    }

    private function sessionEntry(Request $request, FoundationEvent $event): ?EventRegistrationImport
    {
        $id = $request->session()->get(self::SESSION_KEY.'.'.$event->id);

        $entry = $id === null ? null : EventRegistrationImport::query()->where('event_id', $event->id)->find($id);

        return $entry !== null && ! $entry->isCompleted() ? $entry : null;
    }

    /** Release prévue pour la fiche, à défaut la première release active de l'événement. */
    private function releaseFor(FoundationEvent $event, EventRegistrationImport $entry): ?\App\Models\EventRelease
    {
        return $entry->release ?? $event->releases()->where('is_active', true)->first();
    }

    /**
     * Moyens de paiement avec dépôt de preuve (le cash se règle auprès de l'équipe).
     *
     * @return array<string, string> méthode => instructions
     */
    private function depositMethods(FoundationEvent $event): array
    {
        return array_diff_key(EventPayments::enabledMethods($event), ['cash' => true]);
    }

    private function clubTitle(int $clubId): ?string
    {
        return MembershipRequests::clubsByScope(true)->flatten()->firstWhere('id', $clubId)?->title;
    }

    /**
     * @return array{0: string|null, 1: int|null}
     *
     * @throws ValidationException
     */
    private function resolveTarget(?string $scope, mixed $clubId, string $field): array
    {
        if ($scope === null || $scope === '') {
            return [null, null];
        }

        $clubs = MembershipRequests::clubsByScope(true)->get($scope, collect());
        $clubId = $clubId !== null && $clubId !== '' ? (int) $clubId : null;

        if ($clubId === null) {
            if ($clubs->isNotEmpty()) {
                throw ValidationException::withMessages([$field => 'Choisissez votre club dans cette partie.']);
            }

            return [$scope, null];
        }

        if ($clubs->firstWhere('id', $clubId) === null) {
            throw ValidationException::withMessages([$field => 'Ce club est introuvable dans cette partie.']);
        }

        return [$scope, $clubId];
    }

    /** @throws ValidationException */
    private function resolvePosition(?string $scope, ?int $clubId, mixed $positionId, string $field): ?\App\Models\OrgNode
    {
        if ($scope === null || $positionId === null || $positionId === '') {
            return null;
        }

        $position = MembershipRequests::memberPositions($scope, $clubId)->firstWhere('id', (int) $positionId);

        if ($position === null) {
            throw ValidationException::withMessages([$field => 'Ce poste n’existe pas dans cette partie.']);
        }

        return $position;
    }

    /**
     * @return array{0: string, 1: int|null}
     *
     * @throws ValidationException
     */
    private function parseTarget(string $target, string $field): array
    {
        if (preg_match('/^scope:([a-z0-9\-]+)$/', $target, $match) === 1 && OrgScopes::valid($match[1])) {
            return [$match[1], null];
        }

        if (preg_match('/^club:(\d+)$/', $target, $match) === 1) {
            $club = MembershipRequests::clubsByScope(true)->flatten()->firstWhere('id', (int) $match[1]);

            if ($club !== null) {
                return [(string) $club->scope, (int) $club->id];
            }
        }

        throw ValidationException::withMessages([$field => 'Cette partie n’est pas disponible.']);
    }
}
