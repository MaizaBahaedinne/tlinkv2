<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MembershipRequests;
use App\Support\Onboarding;
use App\Support\OrgScopes;
use App\Support\ProfileCompleteness;
use App\Support\ProfileOrganizations;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Hub d'intégration : où en est le nouveau membre, et que lui reste-t-il à faire avant d'accéder à l'application.
 */
class IntegrationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user()->loadMissing('profile');
        $step = Onboarding::current($user);

        if ($step === null) {
            return redirect()->route('home');
        }

        $placement = $step === Onboarding::PLACEMENT ? Onboarding::placementRequest($user) : null;
        $profile = $step === Onboarding::PROFILE ? $user->profile : null;
        $settings = is_array($profile?->privacy_settings) ? $profile->privacy_settings : [];

        return view('onboarding.hub', [
            'profile' => $profile,
            'educations' => array_values(is_array($settings['educations'] ?? null) ? $settings['educations'] : []),
            'languageRows' => array_values(is_array($settings['languages'] ?? null) ? $settings['languages'] : []),
            'skillKeywords' => implode(', ', is_array($settings['skill_keywords'] ?? null) ? $settings['skill_keywords'] : []),
            'schoolOptions' => $step === Onboarding::PROFILE ? (new ProfileOrganizations())->schoolOptions() : [],
            'isAlumni' => $user->member_origin === 'alumni',
            'step' => $step,
            'steps' => Onboarding::STEPS,
            'done' => collect(array_keys(Onboarding::STEPS))->mapWithKeys(fn (string $key) => [$key => Onboarding::done($user, $key)])->all(),
            'missingProfile' => $step === Onboarding::PROFILE ? ProfileCompleteness::missing($user) : [],
            'placement' => $placement,
            'mandate' => $step === Onboarding::PLACEMENT ? Onboarding::currentMandate() : null,
            'scopes' => OrgScopes::all(),
            'clubs' => MembershipRequests::clubsByScope(),
            'motivationMin' => MembershipRequests::MOTIVATION_MIN,
            'motivationMax' => MembershipRequests::MOTIVATION_MAX,
        ]);
    }

    /**
     * Profil rempli directement depuis l'intégration : photo, identité, formation, compétences et langues.
     * Les expériences (facultatives) et les autres blocs du parcours ne sont pas touchés.
     */
    public function saveProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->profile()->firstOrCreate([]);

        $data = $request->validate([
            'avatar' => [blank($profile->photo_path) ? 'required' : 'nullable', 'file', 'image', 'max:5120'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'birth_date' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'address' => ['required', 'string', 'max:255'],
            'facebook_url' => ['required', 'url:http,https', 'max:255', 'regex:#^https?://([a-z0-9-]+\.)*(facebook|fb)\.com(/|$)#i'],
            'educations' => ['nullable', 'array', 'max:25'],
            'educations.*.degree_type' => ['nullable', 'string', 'max:80'],
            'educations.*.diploma_name' => ['nullable', 'string', 'max:180'],
            'educations.*.school' => ['nullable', 'string', 'max:180'],
            'educations.*.start_date' => ['nullable', 'date'],
            'educations.*.end_date' => ['nullable', 'date'],
            'skills' => ['required', 'string', 'max:2000'],
            'languages' => ['nullable', 'array', 'max:25'],
            'languages.*.name' => ['nullable', 'string', 'max:100'],
            'languages.*.level' => ['nullable', 'string', 'max:20'],
        ], [
            'avatar.required' => 'Ajoutez une photo de profil.',
            'gender.required' => 'Indiquez votre genre.',
            'birth_date.required' => 'Indiquez votre date de naissance.',
            'address.required' => 'Indiquez votre adresse.',
            'facebook_url.required' => 'Indiquez le lien de votre page Facebook.',
            'facebook_url.url' => 'Le lien Facebook doit commencer par https://.',
            'facebook_url.regex' => 'Indiquez un lien Facebook (facebook.com ou fb.com).',
            'skills.required' => 'Indiquez au moins une compétence.',
        ]);

        // Les listes déroulantes envoient toujours une valeur : une ligne sans école/diplôme ou sans langue est une ligne vide.
        $educations = array_values(array_filter(
            ProfileController::cleanRows($data['educations'] ?? [], ['degree_type', 'diploma_name', 'school', 'start_date', 'end_date']),
            fn (array $row) => $row['school'] !== '' || $row['diploma_name'] !== '',
        ));
        $languages = array_values(array_filter(
            ProfileController::cleanRows($data['languages'] ?? [], ['name', 'level']),
            fn (array $row) => $row['name'] !== '',
        ));
        $skills = collect(explode(',', $data['skills']))->map(fn (string $item) => trim($item))->filter()->values()->all();

        $errors = [];

        if (collect($educations)->doesntContain(fn (array $row) => $row['school'] !== '' || $row['diploma_name'] !== '')) {
            $errors['educations'] = 'Ajoutez au moins une formation (école ou diplôme).';
        }

        if (collect($languages)->doesntContain(fn (array $row) => $row['name'] !== '')) {
            $errors['languages'] = 'Ajoutez au moins une langue.';
        }

        if ($skills === []) {
            $errors['skills'] = 'Indiquez au moins une compétence.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($request->hasFile('avatar')) {
            if ($profile->photo_path && Storage::disk('public')->exists($profile->photo_path)) {
                Storage::disk('public')->delete($profile->photo_path);
            }

            $profile->photo_path = $request->file('avatar')->store('profiles/avatars', 'public');
        }

        $settings = is_array($profile->privacy_settings) ? $profile->privacy_settings : [];
        $settings['educations'] = $educations;
        $settings['languages'] = $languages;
        $settings['skill_keywords'] = $skills;

        $profile->forceFill([
            'gender' => $data['gender'],
            'birth_date' => $data['birth_date'],
            'address' => $data['address'],
            'facebook_url' => $data['facebook_url'],
            'privacy_settings' => (new ProfileOrganizations())->link($settings, $user->id),
        ])->save();

        return redirect()->route('integration.show')->with('status', 'Votre profil est enregistré.');
    }

    /** Clic sur le lien reçu par e-mail (lien signé). */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('integration.show')->with('status', 'Votre adresse e-mail est confirmée.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('integration.show');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Un nouveau lien de confirmation vient d’être envoyé à '.$user->email.'.');
    }

    /** Adresse saisie par erreur : possible uniquement tant qu'elle n'est pas confirmée. */
    public function changeEmail(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->hasVerifiedEmail(), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'email.unique' => 'Cette adresse est déjà utilisée par un autre compte.',
        ]);

        $user->forceFill(['email' => mb_strtolower($data['email']), 'email_verified_at' => null])->save();
        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Adresse mise à jour : un lien de confirmation vient d’être envoyé à '.$user->email.'.');
    }
}
