<?php

namespace App\Http\Controllers\Admin;

use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Boîtes d'envoi des applications (e-mails de confirmation, notifications…) : réservé à l'administration globale.
 */
class MailController extends AdminController
{
    public function edit(Request $request): View
    {
        $this->authorizeGlobal($request);

        return view('admin.mail.edit', [
            'profiles' => collect(MailSettings::PROFILES)->map(fn (string $label, string $key): array => [
                'label' => $label,
                'data' => MailSettings::profile($key),
                'hasPassword' => MailSettings::hasPassword($key),
                'usable' => MailSettings::usable($key),
            ])->all(),
            'encryptions' => collect(MailSettings::ENCRYPTIONS)->map(fn (array $item): string => $item[0])->all(),
            'ovhHost' => MailSettings::OVH_HOST,
            'envMailer' => config('mail.default'),
        ]);
    }

    public function update(Request $request, string $profile): RedirectResponse
    {
        $this->authorizeGlobal($request);
        $this->profileOrFail($profile);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in(array_keys(MailSettings::ENCRYPTIONS))],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:120'],
        ], [
            'host.regex' => 'Indiquez uniquement le nom du serveur (par exemple ssl0.ovh.net), sans http:// ni port.',
            'username.required' => 'Indiquez l’identifiant de connexion (en général l’adresse e-mail complète).',
            'from_address.required' => 'Indiquez l’adresse qui apparaîtra comme expéditeur.',
        ]);

        if ($request->boolean('enabled') && ($data['password'] ?? '') === '' && ! MailSettings::hasPassword($profile)) {
            return back()->withInput($request->except('password'))->withErrors(['password' => 'Saisissez le mot de passe de la boîte pour l’activer.']);
        }

        MailSettings::save($profile, ['enabled' => $request->boolean('enabled')] + $data);

        // Journal d'audit sans aucune donnée secrète.
        $this->audit($request, 'admin.mail.updated', new \App\Models\Setting(['id' => 0]), [
            'application' => $profile,
            'enabled' => $request->boolean('enabled'),
            'host' => $data['host'],
            'from_address' => $data['from_address'],
            'password_changed' => ($data['password'] ?? '') !== '',
        ]);

        return redirect()->route('admin.mail.edit', ['open' => $profile])->with('status', 'Boîte d’envoi enregistrée : '.MailSettings::PROFILES[$profile].'.');
    }

    public function test(Request $request, string $profile): RedirectResponse
    {
        $this->authorizeGlobal($request);
        $this->profileOrFail($profile);

        $data = $request->validate(['to' => ['required', 'email', 'max:190']], ['to.required' => 'Indiquez l’adresse qui recevra le message de test.']);

        $saved = MailSettings::profile($profile);

        if ($saved['host'] === '' || $saved['username'] === '' || $saved['password'] === '' || $saved['from_address'] === '') {
            return redirect()->route('admin.mail.edit', ['open' => $profile])->withErrors(['test' => 'Enregistrez d’abord la boîte complète (serveur, identifiant, mot de passe, expéditeur).']);
        }

        try {
            Mail::build(MailSettings::mailerConfig($saved))->raw(
                'Ce message confirme que la boîte d’envoi de '.MailSettings::PROFILES[$profile].' fonctionne. Vous pouvez l’ignorer.',
                fn ($message) => $message->to($data['to'])->from($saved['from_address'], $saved['from_name'] ?: config('app.name'))->subject('Test d’envoi | '.MailSettings::PROFILES[$profile]),
            );
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.mail.edit', ['open' => $profile])->withErrors(['test' => 'Échec de l’envoi : '.\Illuminate\Support\Str::limit(strip_tags($exception->getMessage()), 300).' Vérifiez le serveur, le port, le mode de connexion, l’identifiant et le mot de passe.']);
        }

        $this->audit($request, 'admin.mail.tested', new \App\Models\Setting(['id' => 0]), ['application' => $profile, 'to' => $data['to']]);

        return redirect()->route('admin.mail.edit', ['open' => $profile])->with('status', 'Message de test envoyé à '.$data['to'].'. Vérifiez la réception (et les courriers indésirables).');
    }

    private function profileOrFail(string $profile): void
    {
        abort_unless(array_key_exists($profile, MailSettings::PROFILES), 404);
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');
    }
}
