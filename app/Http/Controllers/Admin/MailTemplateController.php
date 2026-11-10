<?php

namespace App\Http\Controllers\Admin;

use App\Notifications\MailTemplateTest;
use App\Support\MailTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Modèles des e-mails automatiques (objet, salutation, corps, bouton, signature) : réservé à l'administration globale.
 */
class MailTemplateController extends AdminController
{
    public function index(Request $request): View
    {
        $this->authorizeGlobal($request);

        return view('admin.mail.templates', [
            'catalog' => MailTemplates::CATALOG,
            'customized' => collect(array_keys(MailTemplates::CATALOG))->mapWithKeys(fn (string $key): array => [$key => MailTemplates::isCustomized($key)])->all(),
        ]);
    }

    public function edit(Request $request, string $template): View
    {
        $this->authorizeGlobal($request);
        $this->templateOrFail($template);

        return view('admin.mail.template-edit', [
            'key' => $template,
            'definition' => MailTemplates::CATALOG[$template],
            'values' => MailTemplates::get($template),
            'defaults' => MailTemplates::CATALOG[$template]['defaults'],
            'customized' => MailTemplates::isCustomized($template),
            'placeholder' => MailTemplates::BUTTON_PLACEHOLDER,
            'preview' => (string) MailTemplates::sample($template)->render(),
        ]);
    }

    public function update(Request $request, string $template): RedirectResponse
    {
        $this->authorizeGlobal($request);
        $this->templateOrFail($template);

        $data = $this->validated($request, $template);

        MailTemplates::save($template, $data);
        $this->audit($request, 'admin.mail.template.updated', new \App\Models\Setting(['id' => 0]), ['template' => $template]);

        return redirect()->route('admin.mail.templates.edit', $template)->with('status', 'Modèle enregistré : '.MailTemplates::CATALOG[$template]['label'].'.');
    }

    public function reset(Request $request, string $template): RedirectResponse
    {
        $this->authorizeGlobal($request);
        $this->templateOrFail($template);

        MailTemplates::reset($template);
        $this->audit($request, 'admin.mail.template.reset', new \App\Models\Setting(['id' => 0]), ['template' => $template]);

        return redirect()->route('admin.mail.templates.edit', $template)->with('status', 'Modèle d’origine rétabli.');
    }

    /** Aperçu du texte saisi (sans l'enregistrer), affiché dans le cadre de la page d'édition. */
    public function preview(Request $request, string $template): Response
    {
        $this->authorizeGlobal($request);
        $this->templateOrFail($template);

        $draft = [];

        foreach (MailTemplates::FIELDS as $field) {
            $draft[$field] = Str::limit((string) $request->input($field, ''), 4000, '');
        }

        return response((string) MailTemplates::sample($template, $draft)->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** Envoie le modèle enregistré, avec des valeurs d'exemple, à l'adresse indiquée. */
    public function test(Request $request, string $template): RedirectResponse
    {
        $this->authorizeGlobal($request);
        $this->templateOrFail($template);

        $data = $request->validate(['to' => ['required', 'email', 'max:190']], ['to.required' => 'Indiquez l’adresse qui recevra le message de test.']);

        try {
            Notification::route('mail', $data['to'])->notify(new MailTemplateTest($template));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.mail.templates.edit', $template)->withErrors(['test' => 'Échec de l’envoi : '.Str::limit(strip_tags($exception->getMessage()), 300).' Vérifiez la boîte d’envoi de T.Link (Messagerie).']);
        }

        $this->audit($request, 'admin.mail.tested', new \App\Models\Setting(['id' => 0]), ['template' => $template, 'to' => $data['to']]);

        return redirect()->route('admin.mail.templates.edit', $template)->with('status', 'Message de test envoyé à '.$data['to'].'. Il apparaît dans le journal des envois.');
    }

    /**
     * @return array<string, string>
     */
    private function validated(Request $request, string $template): array
    {
        $definition = MailTemplates::CATALOG[$template];

        $rules = [
            'subject' => ['required', 'string', 'max:190'],
            'greeting' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:4000'],
            'salutation' => ['required', 'string', 'max:190'],
            'button_label' => [$definition['button'] ? 'required' : 'nullable', 'string', 'max:80'],
        ];

        $data = $request->validate($rules, [
            'subject.required' => 'L’objet de l’e-mail est obligatoire.',
            'greeting.required' => 'La formule d’accueil est obligatoire.',
            'body.required' => 'Le message est obligatoire.',
            'body.max' => 'Le message est limité à 4000 caractères.',
            'salutation.required' => 'La signature est obligatoire.',
            'button_label.required' => 'Le texte du bouton est obligatoire pour ce modèle.',
        ]);

        $data['button_label'] = (string) ($data['button_label'] ?? '');

        $allowed = array_keys($definition['variables']);

        foreach (['subject', 'greeting', 'body', 'button_label', 'salutation'] as $field) {
            preg_match_all('/\{([A-Za-z_]+)\}/', $data[$field], $found);

            foreach (array_unique($found[1]) as $name) {
                if (! in_array($name, $allowed, true)) {
                    throw ValidationException::withMessages([$field => 'Variable inconnue : {'.$name.'}. Variables disponibles : '.collect($allowed)->map(fn (string $v): string => '{'.$v.'}')->implode(', ').'.']);
                }
            }
        }

        // Les variables indispensables doivent figurer dans le message lui-même (l'objet seul ne suffit pas).
        foreach ($definition['required'] as $required) {
            if (! str_contains($data['body'], '{'.$required.'}')) {
                throw ValidationException::withMessages(['body' => 'Le message doit contenir la variable {'.$required.'} : sans elle le destinataire ne peut pas poursuivre.']);
            }
        }

        return $data;
    }

    private function templateOrFail(string $template): void
    {
        abort_unless(array_key_exists($template, MailTemplates::CATALOG), 404);
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');
    }
}
