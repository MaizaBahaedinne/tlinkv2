<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Support\LaunchGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Compte à rebours de lancement du site et de T.Link (réservé à l'administration globale).
 */
class LaunchController extends AdminController
{
    public function edit(Request $request): View
    {
        $this->authorizeGlobal($request);

        $settings = LaunchGate::settings();

        return view('admin.launch.edit', [
            'settings' => $settings,
            'launchAt' => LaunchGate::launchAt($settings),
            'closed' => LaunchGate::isClosed($settings),
            'allowedEmails' => User::query()->whereIn('id', $settings['allowed_user_ids'])->orderBy('email')->pluck('email')->implode("\n"),
            'defaults' => ['title' => LaunchGate::DEFAULT_TITLE, 'message' => LaunchGate::DEFAULT_MESSAGE],
            'timezone' => config('app.timezone'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'include_app' => ['nullable', 'boolean'],
            'launch_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'required_if:enabled,1'],
            'title' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
            'allowed_emails' => ['nullable', 'string', 'max:10000'],
        ], [
            'launch_at.required_if' => 'Indiquez la date et l’heure d’ouverture pour activer le compte à rebours.',
        ]);

        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['allowed_emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $email): string => mb_strtolower($email))
            ->unique()
            ->values();
        $users = User::query()->whereIn(DB::raw('lower(email)'), $emails)->get(['id', 'email']);
        $unknown = $emails->diff($users->pluck('email')->map(fn (string $email): string => mb_strtolower($email)));

        if ($unknown->isNotEmpty()) {
            return back()->withInput()->withErrors(['allowed_emails' => 'Aucun compte pour : '.$unknown->implode(', ').'.']);
        }

        $setting = LaunchGate::save([
            'allowed_user_ids' => $users->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'enabled' => $request->boolean('enabled'),
            'include_app' => $request->boolean('include_app'),
            'launch_at' => (string) ($data['launch_at'] ?? ''),
            'title' => trim((string) ($data['title'] ?? '')),
            'message' => trim((string) ($data['message'] ?? '')),
        ]);

        $this->audit($request, 'admin.launch.updated', $setting, [
            'enabled' => $request->boolean('enabled'),
            'include_app' => $request->boolean('include_app'),
            'launch_at' => $data['launch_at'] ?? null,
            'allowed_user_ids' => $users->pluck('id')->all(),
        ]);

        return back()->with('status', $request->boolean('enabled')
            ? 'Compte à rebours activé : le site est fermé au public jusqu’à la date d’ouverture.'
            : 'Compte à rebours désactivé : le site est ouvert à tous.');
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');
    }
}
