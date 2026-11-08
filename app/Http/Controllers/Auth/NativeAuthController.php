<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Domains;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Connexion des applications mobiles par le navigateur du téléphone (Google refuse l'authentification dans une WebView).
 *
 * 1. L'application ouvre « start » dans le navigateur, avec un défi (SHA-256 d'un secret gardé dans l'application).
 * 2. Le navigateur connecte l'utilisateur (Google, mot de passe, ou session déjà ouverte : c'est ce qui donne la connexion
 *    automatique d'une application T.Link à l'autre), puis rend la main à l'application par son schéma d'adresse.
 * 3. L'application présente le jeton à « consume » avec son secret : la session s'ouvre dans la WebView.
 * Le jeton est à usage unique, valable 2 minutes, et inutilisable sans le secret (une autre application interceptant
 * le schéma d'adresse ne peut donc pas s'en servir).
 */
class NativeAuthController extends Controller
{
    private const TTL_MINUTES = 2;

    public function start(Request $request): RedirectResponse|View
    {
        $appId = (string) $request->query('app');
        abort_unless(is_array(config('mobile.apps')[$appId] ?? null), 404);

        $challenge = (string) $request->query('challenge');
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $challenge) === 1, 422);

        if (! Auth::check()) {
            $request->session()->put('url.intended', $request->fullUrl());
            $provider = $request->query('provider');

            return is_string($provider) && array_key_exists($provider, SocialAuthController::PROVIDERS)
                ? redirect()->route('social.redirect', ['provider' => $provider])
                : redirect()->route('login');
        }

        $token = Str::random(64);
        Cache::put(self::cacheKey($token), ['user' => Auth::id(), 'challenge' => $challenge, 'app' => $appId], now()->addMinutes(self::TTL_MINUTES));

        return view('auth.native-handoff', ['link' => $appId.'://auth?token='.$token]);
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        // pull() : le jeton est détruit dès la première présentation, valide ou non.
        $data = Cache::pull(self::cacheKey($token));
        $verifier = (string) $request->query('v');
        $user = is_array($data) && hash_equals((string) $data['challenge'], hash('sha256', $verifier))
            ? User::query()->find($data['user'])
            : null;

        if (! $user || $user->is_active === false) {
            return redirect()->route('login')->withErrors(['email' => 'La connexion depuis le navigateur a expiré. Réessaie.']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $app = config('mobile.apps')[$data['app']];

        return redirect()->to(Domains::url($app['section'], $app['path']));
    }

    private static function cacheKey(string $token): string
    {
        return 'native-login:'.hash('sha256', $token);
    }
}
