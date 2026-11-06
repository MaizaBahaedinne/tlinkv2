<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\MembershipRequests;
use App\Support\Onboarding;
use App\Support\OrgScopes;
use App\Support\ProfileCompleteness;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        return view('onboarding.hub', [
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
