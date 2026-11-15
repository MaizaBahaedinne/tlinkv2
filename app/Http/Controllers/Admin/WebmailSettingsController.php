<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use App\Support\Webmail\OvhApi;
use App\Support\Webmail\WebmailException;
use App\Support\Webmail\WebmailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Connexion du webmail à OVH : clés de l'API (création des boîtes), domaine, serveurs. Réservé à l'administration globale. */
class WebmailSettingsController extends AdminController
{
    public function edit(Request $request): View
    {
        $this->authorizeGlobal($request);

        return view('admin.webmail.settings', [
            'config' => WebmailSettings::get(),
            'endpoints' => collect(WebmailSettings::ENDPOINTS)->map(fn (array $item): string => $item[0])->all(),
            'hasSecret' => WebmailSettings::hasSecret('application_secret'),
            'hasConsumer' => WebmailSettings::hasSecret('consumer_key'),
            'ready' => WebmailSettings::apiReady(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $data = $request->validate([
            'endpoint' => ['required', Rule::in(array_keys(WebmailSettings::ENDPOINTS))],
            'application_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9]*$/'],
            'application_secret' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9]*$/'],
            'consumer_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9]*$/'],
            'domain' => ['required', 'string', 'max:190', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i'],
            'imap_host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/'],
            'imap_port' => ['required', 'integer', Rule::in([143, 993])],
            'smtp_host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/'],
            'smtp_port' => ['required', 'integer', Rule::in([465, 587])],
            'mailbox_size' => ['nullable', 'integer', 'min:100', 'max:1000000'],
        ], [
            'domain.regex' => 'Indiquez le domaine seul, par exemple fondation-tunivisions.org.',
            'imap_host.regex' => 'Indiquez uniquement le nom du serveur (par exemple ssl0.ovh.net).',
            'smtp_host.regex' => 'Indiquez uniquement le nom du serveur (par exemple ssl0.ovh.net).',
            'application_key.regex' => 'La clé d’application ne contient que des lettres et des chiffres.',
            'application_secret.regex' => 'Le secret d’application ne contient que des lettres et des chiffres.',
            'consumer_key.regex' => 'La clé de consommateur ne contient que des lettres et des chiffres.',
        ]);

        WebmailSettings::save($data);

        $this->audit($request, 'admin.webmail.settings.updated', new Setting(['id' => 0]), [
            'domain' => $data['domain'],
            'endpoint' => $data['endpoint'],
            'secret_changed' => filled($data['application_secret'] ?? null),
            'consumer_changed' => filled($data['consumer_key'] ?? null),
        ]);

        return redirect()->route('admin.webmail.settings.edit')->with('status', 'Connexion du webmail enregistrée.');
    }

    /** Vérifie les clés : liste les boîtes du domaine. */
    public function test(Request $request, OvhApi $ovh): RedirectResponse
    {
        $this->authorizeGlobal($request);

        try {
            $count = count($ovh->accounts());
        } catch (WebmailException $exception) {
            return redirect()->route('admin.webmail.settings.edit')->withErrors(['test' => $exception->getMessage()]);
        }

        return redirect()->route('admin.webmail.settings.edit')->with('status', 'Connexion OVH valide : '.$count.' boîte(s) existante(s) sur ce domaine.');
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');
    }
}
