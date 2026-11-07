<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Support\CurrentStatus;
use App\Support\EmailVerificationCodes;
use App\Support\MembershipRequests;
use App\Support\Onboarding;
use App\Support\OrgScopes;
use App\Support\ProfileCompleteness;
use App\Support\ProfileOrganizations;
use Illuminate\Auth\Events\Verified;
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
        $profile = in_array($step, [Onboarding::PROFILE, Onboarding::STATUS], true) ? $user->profile : null;
        $settings = is_array($profile?->privacy_settings) ? $profile->privacy_settings : [];

        return view('onboarding.hub', [
            'profile' => $profile,
            'educations' => array_values(is_array($settings['educations'] ?? null) ? $settings['educations'] : []),
            'languageRows' => array_values(is_array($settings['languages'] ?? null) ? $settings['languages'] : []),
            'skillKeywords' => implode(', ', is_array($settings['skill_keywords'] ?? null) ? $settings['skill_keywords'] : []),
            'schoolOptions' => $step === Onboarding::PROFILE ? (new ProfileOrganizations())->schoolOptions() : [],
            'isAlumni' => $user->member_origin === 'alumni',
            'statusKinds' => CurrentStatus::KINDS,
            'pupilLevels' => CurrentStatus::PUPIL_LEVELS,
            'studentLevels' => CurrentStatus::STUDENT_LEVELS,
            'countries' => $step === Onboarding::STATUS ? CurrentStatus::countries() : [],
            'statusCompany' => $step === Onboarding::STATUS && $profile?->status_company_id ? Company::query()->find($profile->status_company_id) : null,
            'companyOptions' => $step === Onboarding::STATUS ? (new ProfileOrganizations())->companyOptions() : [],
            'governorates' => $step === Onboarding::PROFILE ? \App\Models\Governorate::query()->orderBy('name')->get(['id', 'name']) : [],
            'step' => $step,
            'steps' => [...Onboarding::STEPS, Onboarding::PLACEMENT => $user->member_origin === 'alumni' ? 'Rejoindre une entité, un club ou un poste' : 'Rejoindre un club'],
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
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'delegation' => ['required', 'string', 'max:120'],
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
            'governorate_id.required' => 'Choisissez votre gouvernorat.',
            'governorate_id.exists' => 'Ce gouvernorat n’existe pas : choisissez-le dans la liste.',
            'delegation.required' => 'Indiquez votre délégation.',
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
            'governorate_id' => $data['governorate_id'],
            'delegation' => trim($data['delegation']),
            'facebook_url' => $data['facebook_url'],
            'privacy_settings' => (new ProfileOrganizations())->link($settings, $user->id),
        ])->save();

        return redirect()->route('integration.show')->with('status', 'Votre profil est enregistré.');
    }

    /**
     * Statut actuel : élève, étudiant, en recherche d'emploi, salarié, chef d'entreprise ou diaspora.
     * Un chef d'entreprise ajoute sa société à la base (logo + étiquette « membre Tunivisions »)
     * et celle-ci devient une expérience professionnelle de son profil.
     */
    public function saveStatus(Request $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->profile()->firstOrCreate([]);
        $kind = (string) $request->input('status_kind');
        $orgs = new ProfileOrganizations();

        $rules = ['status_kind' => ['required', Rule::in(array_keys(CurrentStatus::KINDS))]];

        if (CurrentStatus::usesLevel($kind)) {
            $rules['status_level'] = ['required', Rule::in(array_keys(CurrentStatus::levels($kind)))];
        }

        if ($kind === CurrentStatus::DIASPORA) {
            $rules['status_country'] = ['required', Rule::in(CurrentStatus::countries())];
        }

        if ($kind === CurrentStatus::ENTREPRENEUR) {
            $key = ProfileOrganizations::key((string) $request->input('company_name'));
            $existing = $key === '' ? null : Company::query()->where('slug', $key)->first();

            $rules['company_name'] = ['required', 'string', 'max:160'];
            $rules['started_on'] = ['required', 'date', 'after:1900-01-01', 'before_or_equal:today'];
            $rules['logo'] = [filled($existing?->logo_path) ? 'nullable' : 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'];
        }

        $data = $request->validate($rules, [
            'status_kind.required' => 'Choisissez votre statut actuel.',
            'status_kind.in' => 'Ce statut n’existe pas : choisissez-le dans la liste.',
            'status_level.required' => 'Indiquez votre niveau.',
            'status_level.in' => 'Ce niveau n’existe pas : choisissez-le dans la liste.',
            'status_country.required' => 'Indiquez le pays où vous vivez.',
            'status_country.in' => 'Ce pays n’est pas dans la liste.',
            'company_name.required' => 'Indiquez le nom de votre société.',
            'started_on.required' => 'Indiquez la date de début de votre activité.',
            'started_on.before_or_equal' => 'Cette date ne peut pas être dans le futur.',
            'logo.required' => 'Ajoutez le logo de votre société.',
            'logo.image' => 'Le logo doit être une image (JPG, PNG ou WebP).',
            'logo.mimes' => 'Le logo doit être une image (JPG, PNG ou WebP).',
            'logo.max' => 'Le logo est trop lourd (3 Mo maximum).',
        ]);

        $company = null;
        $settings = is_array($profile->privacy_settings) ? $profile->privacy_settings : [];

        if ($kind === CurrentStatus::ENTREPRENEUR) {
            $company = $orgs->resolveCompany($data['company_name'], $user->id);
            $company->tunivisions_member = true;

            if ($request->hasFile('logo') && blank($company->logo_path)) {
                $company->logo_path = $request->file('logo')->store('companies/logos', 'public');
            }

            $company->save();

            // La société devient une expérience professionnelle (mise à jour si elle y figure déjà).
            $rows = array_values(is_array($settings['professional_experiences'] ?? null) ? $settings['professional_experiences'] : []);
            $found = false;

            foreach ($rows as $index => $row) {
                if (ProfileOrganizations::key((string) ($row['company'] ?? '')) === $company->slug && ($row['role'] ?? '') === CurrentStatus::ENTREPRENEUR_ROLE) {
                    $rows[$index]['start_date'] = $data['started_on'];
                    $found = true;
                }
            }

            if (! $found) {
                $rows[] = ['company' => $company->name, 'role' => CurrentStatus::ENTREPRENEUR_ROLE, 'start_date' => $data['started_on'], 'end_date' => '', 'description' => ''];
            }

            $settings['professional_experiences'] = $rows;
        }

        $profile->forceFill([
            'status_kind' => $kind,
            'status_level' => CurrentStatus::usesLevel($kind) ? $data['status_level'] : null,
            'status_country' => $kind === CurrentStatus::DIASPORA ? $data['status_country'] : null,
            'status_company_id' => $company?->id,
            'privacy_settings' => $orgs->link($settings, $user->id),
        ])->save();

        return redirect()->route('integration.show')->with('status', 'Votre statut est enregistré.');
    }

    /** Code à 6 chiffres reçu par e-mail (valable 15 minutes, 5 essais). */
    public function verify(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('integration.show');
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^\s*\d{3}\s?\d{3}\s*$/'],
        ], [
            'code.required' => 'Saisissez le code reçu par e-mail.',
            'code.regex' => 'Le code comporte 6 chiffres.',
        ]);

        $result = EmailVerificationCodes::check($user, $data['code']);

        if ($result !== EmailVerificationCodes::OK) {
            throw ValidationException::withMessages(['code' => match ($result) {
                EmailVerificationCodes::EXPIRED => 'Ce code a expiré (il est valable '.EmailVerificationCodes::VALID_MINUTES.' minutes) : demandez-en un nouveau.',
                EmailVerificationCodes::LOCKED => 'Trop d’essais : ce code n’est plus valable, demandez-en un nouveau.',
                default => 'Code incorrect : vérifiez le dernier e-mail reçu (un nouveau code rend l’ancien inutilisable).',
            }]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->route('integration.show')->with('status', 'Votre adresse e-mail est confirmée.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('integration.show');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Un nouveau code vient d’être envoyé à '.$user->email.' ; il est valable '.EmailVerificationCodes::VALID_MINUTES.' minutes.');
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

        return back()->with('status', 'Adresse mise à jour : un code de confirmation vient d’être envoyé à '.$user->email.'.');
    }
}
