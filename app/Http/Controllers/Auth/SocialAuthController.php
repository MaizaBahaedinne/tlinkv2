<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Contracts\User as SocialUser;
use Throwable;

/**
 * Connexion et inscription via Google, Facebook ou LinkedIn.
 */
class SocialAuthController extends Controller
{
    /** Reseaux supportes : cle de route => libelle. */
    public const PROVIDERS = [
        'google' => 'Google',
        'facebook' => 'Facebook',
        'linkedin-openid' => 'LinkedIn',
    ];

    /**
     * Reseaux dont les identifiants sont renseignes (ceux qui affichent un bouton).
     *
     * @return array<string, string>
     */
    public static function enabled(): array
    {
        return array_filter(
            self::PROVIDERS,
            fn (string $label, string $key): bool => filled(config("services.{$key}.client_id"))
                && filled(config("services.{$key}.client_secret")),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function redirect(string $provider): RedirectResponse
    {
        if ($unavailable = $this->unavailable($provider)) {
            return $unavailable;
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if ($unavailable = $this->unavailable($provider)) {
            return $unavailable;
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return $this->fail('La connexion avec '.self::PROVIDERS[$provider].' a echoue. Reessaie ou utilise ton email.');
        }

        $email = Str::lower(trim((string) $socialUser->getEmail()));

        if ($email === '' && ! SocialAccount::query()->where('provider', $provider)->where('provider_id', $socialUser->getId())->exists()) {
            return $this->fail(self::PROVIDERS[$provider].' ne nous a transmis aucune adresse email. Utilise un autre moyen de connexion.');
        }

        $user = DB::transaction(fn (): ?User => $this->resolveUser($provider, $socialUser, $email));

        if (! $user) {
            return $this->fail('Un compte existe deja avec cet email, mais '.self::PROVIDERS[$provider].' ne l\'a pas verifie. Connecte-toi avec ton mot de passe.');
        }

        if ($user->is_active === false) {
            return $this->fail('Ce compte est desactive.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function resolveUser(string $provider, SocialUser $socialUser, string $email): ?User
    {
        $linked = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', (string) $socialUser->getId())
            ->first();

        if ($linked) {
            return $linked->user;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            // On ne rattache un compte existant que si le reseau garantit la propriete de l'email.
            if (! $this->emailIsVerified($provider, $socialUser)) {
                return null;
            }

            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        } else {
            [$first, $last] = $this->names($socialUser);

            $user = User::query()->create([
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'password' => Str::random(48),
            ]);

            $user->forceFill(['email_verified_at' => $this->emailIsVerified($provider, $socialUser) ? now() : null])->save();

            Profile::query()->create([
                'user_id' => $user->id,
                'headline' => 'Nouveau membre Fondation Tunivisions',
            ]);

            event(new Registered($user));
        }

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => (string) $socialUser->getId(),
            'email' => $email,
        ]);

        return $user;
    }

    private function emailIsVerified(string $provider, SocialUser $socialUser): bool
    {
        // Facebook ne renvoie que des emails confirmes ; Google et LinkedIn l'indiquent explicitement.
        if ($provider === 'facebook') {
            return true;
        }

        $raw = (array) ($socialUser->user ?? []);

        return filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function names(SocialUser $socialUser): array
    {
        $raw = (array) ($socialUser->user ?? []);
        $first = trim((string) ($raw['given_name'] ?? $raw['first_name'] ?? ''));
        $last = trim((string) ($raw['family_name'] ?? $raw['last_name'] ?? ''));

        if ($first === '' && $last === '') {
            [$first, $last] = User::splitName((string) ($socialUser->getName() ?: Str::before((string) $socialUser->getEmail(), '@')));
        }

        return [Str::limit((string) $first, 120, ''), Str::limit((string) $last, 120, '')];
    }

    /**
     * 404 pour un reseau inconnu ; retour a la connexion avec un message si ses identifiants manquent.
     */
    private function unavailable(string $provider): ?RedirectResponse
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);

        return array_key_exists($provider, self::enabled())
            ? null
            : $this->fail('La connexion avec '.self::PROVIDERS[$provider].' n\'est pas encore activee. Utilise ton email pour le moment.');
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
