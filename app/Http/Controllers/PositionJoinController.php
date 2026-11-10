<?php

namespace App\Http\Controllers;

use App\Models\Governorate;
use App\Models\OrgNode;
use App\Models\PositionApplication;
use App\Models\PositionInvitation;
use App\Models\Profile;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\LaunchGate;
use App\Support\OrgChart\OrgChartRules;
use Illuminate\Auth\Events\Registered;
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
 * Lien d'inscription direct d'un poste de l'organigramme : inscription complète (compte + profil + avatar)
 * qui dépose une candidature. Rien n'est attribué automatiquement : un administrateur doit accepter.
 */
class PositionJoinController extends Controller
{
    public function __construct(private readonly OrgChartRules $rules)
    {
    }

    public function show(Request $request, string $token): View
    {
        $node = $this->nodeFor($token);
        $user = $request->user();

        if (! $user) {
            // Apres une connexion, on revient sur cette page.
            $request->session()->put('url.intended', $request->url());
        }

        return view('position-join', [
            'node' => $node,
            'closed' => $this->isTaken($node),
            'existing' => $user ? $this->existingApplication($node, $user) : null,
            'alreadyHolder' => $user ? $this->isHolder($node, $user) : false,
            'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name']),
            'invitation' => null,
            'invalid' => null,
            'action' => route('position.join.store', $node->join_token),
        ]);
    }

    /**
     * Lien personnel reçu par e-mail : l'e-mail est prérempli (et fait foi), la personne crée son compte puis candidate.
     * Si un compte existe déjà avec cette adresse, elle se connecte puis revient ici.
     */
    public function showInvitation(Request $request, string $token): View|RedirectResponse
    {
        $invitation = $this->invitationFor($token);
        $node = $invitation->node;
        $user = $request->user();

        $invalid = match ($invitation->status()) {
            'revoked' => 'Cette invitation a été annulée. Contactez la Fondation pour en recevoir une nouvelle.',
            'expired' => 'Cette invitation a expiré. Contactez la Fondation pour en recevoir une nouvelle.',
            'accepted' => $user ? null : 'Cette invitation a déjà été utilisée. Connectez-vous pour suivre votre candidature.',
            default => null,
        };

        if ($invalid === null && ! $user && User::query()->where('email', $invitation->email)->exists()) {
            $request->session()->put('url.intended', $request->url());

            return redirect()->route('login')->with('status', 'Un compte existe déjà avec l’adresse '.$invitation->email.' : connectez-vous pour candidater au poste « '.$node->title.' ».');
        }

        return view('position-join', [
            'node' => $node,
            'closed' => $invalid === null && $this->isTaken($node),
            'existing' => $user ? $this->existingApplication($node, $user) : null,
            'alreadyHolder' => $user ? $this->isHolder($node, $user) : false,
            'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name']),
            'invitation' => $invitation,
            'invalid' => $invalid,
            'action' => route('position.invitation.store', $token),
        ]);
    }

    public function storeInvitation(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitationFor($token);
        $node = $invitation->node;

        if (! $invitation->isOpen()) {
            throw ValidationException::withMessages(['message' => 'Cette invitation n’est plus valide : demandez-en une nouvelle à la Fondation.']);
        }

        if ($this->isTaken($node)) {
            throw ValidationException::withMessages(['message' => 'Ce poste est déjà pourvu : les candidatures sont fermées.']);
        }

        $user = $request->user();

        if ($user) {
            return $this->applyExisting($request, $node, $user, $invitation);
        }

        // L'adresse de l'invitation fait foi : elle ne peut pas être remplacée dans le formulaire.
        $request->merge(['email' => $invitation->email]);

        return $this->register($request, $node, $invitation);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $node = $this->nodeFor($token);

        if ($this->isTaken($node)) {
            throw ValidationException::withMessages(['message' => 'Ce poste est déjà pourvu : les candidatures sont fermées.']);
        }

        $user = $request->user();

        if ($user) {
            return $this->applyExisting($request, $node, $user);
        }

        return $this->register($request, $node);
    }

    /** Crée le compte, le profil et la candidature ; avec une invitation, l'adresse est déjà prouvée par la réception du lien. */
    private function register(Request $request, OrgNode $node, ?PositionInvitation $invitation = null): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+().\-\s]{6,20}$/'],
            'phone_secondary' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+().\-\s]{6,20}$/'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birth_date' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'delegation' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:255'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:255'],
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'avatar.required' => 'Une photo de profil est obligatoire.',
            'message.required' => 'Expliquez brièvement votre motivation.',
        ]);

        $avatarPath = $request->file('avatar')->store('profiles/avatars', 'public');

        try {
            $user = DB::transaction(function () use ($data, $node, $avatarPath, $invitation): User {
                $user = User::query()->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);

                if ($invitation) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                Profile::query()->create([
                    'user_id' => $user->id,
                    'headline' => ($data['headline'] ?? null) ?: 'Candidat(e) au poste : '.$node->title,
                    'phone' => $data['phone'],
                    'phone_secondary' => $data['phone_secondary'] ?? null,
                    'gender' => $data['gender'],
                    'birth_date' => $data['birth_date'],
                    'governorate_id' => $data['governorate_id'],
                    'delegation' => $data['delegation'] ?? null,
                    'address' => $data['address'] ?? null,
                    'bio' => $data['bio'] ?? null,
                    'facebook_url' => $data['facebook_url'] ?? null,
                    'instagram_url' => $data['instagram_url'] ?? null,
                    'linkedin_url' => $data['linkedin_url'] ?? null,
                    'photo_path' => $avatarPath,
                ]);

                $this->createApplication($node, $user, $data['message'], $invitation);

                return $user;
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($avatarPath);

            throw $e;
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return $this->afterApplication($node, 'Votre compte est créé et votre candidature au poste « '.$node->title.' » est enregistrée : elle sera examinée par l’équipe.');
    }

    private function applyExisting(Request $request, OrgNode $node, User $user, ?PositionInvitation $invitation = null): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']], [
            'message.required' => 'Expliquez brièvement votre motivation.',
        ]);

        if ($this->isHolder($node, $user)) {
            throw ValidationException::withMessages(['message' => 'Vous occupez déjà ce poste.']);
        }

        if ($this->existingApplication($node, $user)?->status === 'pending') {
            throw ValidationException::withMessages(['message' => 'Votre candidature à ce poste est déjà en cours d’examen.']);
        }

        $this->createApplication($node, $user, $data['message'], $invitation);

        return $this->afterApplication($node, 'Votre candidature au poste « '.$node->title.' » est enregistrée : elle sera examinée par l’équipe.');
    }

    /**
     * Pendant le compte à rebours de lancement, le fil T.Link est fermé : on confirme sur la page du poste.
     */
    private function afterApplication(OrgNode $node, string $status): RedirectResponse
    {
        $target = LaunchGate::isClosed() ? route('position.join', $node->join_token) : route('home');

        return redirect($target)->with('status', $status);
    }

    private function createApplication(OrgNode $node, User $user, string $message, ?PositionInvitation $invitation = null): PositionApplication
    {
        $application = PositionApplication::query()->create([
            'node_id' => $node->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'message' => $message,
        ]);

        AuditLogger::record($user->id, 'position.application.created', PositionApplication::class, $application->id, ['position' => $node->title], request()->ip());

        if ($invitation && $invitation->isOpen() && mb_strtolower($user->email) === mb_strtolower($invitation->email)) {
            $invitation->forceFill(['accepted_at' => now(), 'user_id' => $user->id])->save();
            AuditLogger::record($user->id, 'position.invitation.accepted', PositionInvitation::class, $invitation->id, ['position' => $node->title], request()->ip());
        }

        return $application;
    }

    private function invitationFor(string $token): PositionInvitation
    {
        return PositionInvitation::findByToken($token) ?? abort(404);
    }

    private function nodeFor(string $token): OrgNode
    {
        return OrgNode::query()->where('kind', 'position')->where('join_token', $token)->firstOrFail();
    }

    /** Poste à titulaire unique déjà occupé. */
    private function isTaken(OrgNode $node): bool
    {
        return $node->capacity !== 'multiple' && $this->rules->currentPeopleCount($node) >= 1;
    }

    private function isHolder(OrgNode $node, User $user): bool
    {
        return $node->assignments()->whereHas('person', fn ($query) => $query->where('user_id', $user->id))->exists();
    }

    private function existingApplication(OrgNode $node, User $user): ?PositionApplication
    {
        return PositionApplication::query()->where('node_id', $node->id)->where('user_id', $user->id)->latest('id')->first();
    }
}
