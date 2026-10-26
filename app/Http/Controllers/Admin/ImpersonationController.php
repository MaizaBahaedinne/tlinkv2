<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Support\AdminContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Permet à un administrateur global de se connecter temporairement en tant qu'un autre utilisateur
 * (assistance, vérification des droits d'un poste). Chaque début et fin est tracé dans le journal d'audit.
 */
class ImpersonationController extends AdminController
{
    public const SESSION_KEY = 'impersonator_id';

    public static function canImpersonate(Request $request, User $target): bool
    {
        return AdminContext::of($request)->isGlobal('entity.manage')
            && ! $request->session()->has(self::SESSION_KEY)
            && $target->is_active
            && ! $target->is($request->user())
            // Un autre administrateur global ne peut pas être incarné : on ne contourne pas la traçabilité entre pairs.
            && ! (new AdminContext($target))->isGlobal('entity.manage');
    }

    public function start(Request $request, User $user): RedirectResponse
    {
        abort_unless(self::canImpersonate($request, $user), 403, 'Vous ne pouvez pas vous connecter en tant que cet utilisateur.');

        $admin = $request->user();

        $this->audit($request, 'admin.impersonation.started', $user, ['target' => $user->name]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $admin->id);

        return redirect()->route('home')->with('status', 'Vous êtes connecté(e) en tant que '.$user->name.'.');
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get(self::SESSION_KEY);

        abort_unless($adminId, 403, 'Aucune session d’incarnation en cours.');

        $target = $request->user();
        $admin = User::query()->find($adminId);

        $request->session()->forget(self::SESSION_KEY);

        if (! $admin || ! $admin->is_active || ! (new AdminContext($admin))->isGlobal('entity.manage')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Votre session d’administrateur n’est plus valide : reconnectez-vous.']);
        }

        \App\Support\AuditLogger::record($admin->id, 'admin.impersonation.stopped', User::class, $target?->id, ['target' => $target?->name], $request->ip());

        Auth::login($admin);
        $request->session()->regenerate();

        return $target
            ? redirect()->route('admin.users.show', $target)->with('status', 'Vous êtes de retour sur votre compte.')
            : redirect()->route('admin.dashboard');
    }
}
