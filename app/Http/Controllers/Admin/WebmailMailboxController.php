<?php

namespace App\Http\Controllers\Admin;

use App\Models\WebmailMailbox;
use App\Support\MarketingEntities;
use App\Support\Webmail\GatewayFactory;
use App\Support\Webmail\OvhApi;
use App\Support\Webmail\WebmailAccess;
use App\Support\Webmail\WebmailException;
use App\Support\Webmail\WebmailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Boîtes e-mail des entités. T.Link crée la boîte chez OVH, en génère le mot de passe et le garde chiffré : personne ne
 * le connaît ni ne le voit, les membres autorisés lisent la boîte depuis le webmail. Une boîte déjà créée chez OVH peut
 * aussi être enregistrée avec son mot de passe.
 */
class WebmailMailboxController extends AdminController
{
    private const MODULE = 'webmail.mailboxes';

    public function index(Request $request): View
    {
        $user = $request->user();
        $context = $this->context($request);

        return view('admin.webmail.mailboxes.index', [
            'mailboxes' => MarketingEntities::restrict(WebmailMailbox::query(), $user)->orderBy('address')->paginate(20),
            'domain' => WebmailSettings::domain(),
            'canCreate' => $context->allowsModule(['event.manage'], self::MODULE, ['create']),
            'canUpdate' => $context->allowsModule(['event.manage'], self::MODULE, ['update']),
            'canDelete' => $context->allowsModule(['event.manage'], self::MODULE, ['delete']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        return view('admin.webmail.mailboxes.form', $this->formData($request, new WebmailMailbox(['is_active' => true])));
    }

    public function store(Request $request, OvhApi $ovh): RedirectResponse
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        $reach = MarketingEntities::reach($request->user());
        $domain = WebmailSettings::domain();

        $data = $request->validate([
            'entity' => ['required', 'string', Rule::in($reach->keys()->all())],
            'local_part' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'signature' => ['nullable', 'string', 'max:1500'],
            'mode' => ['required', Rule::in(['ovh', 'existing'])],
            'password' => ['nullable', 'string', 'min:8', 'max:128', Rule::requiredIf($request->input('mode') === 'existing')],
        ], [
            'entity.in' => 'Cette entité est hors de votre périmètre.',
            'local_part.regex' => 'Minuscules, chiffres, point, tiret ou tiret bas (par exemple « contact »).',
            'password.required' => 'Saisissez le mot de passe de la boîte déjà créée chez OVH.',
        ]);

        if ($domain === '') {
            throw ValidationException::withMessages(['local_part' => 'Renseignez d’abord le domaine dans « Connexion OVH ».']);
        }

        $address = $data['local_part'].'@'.$domain;

        if (WebmailMailbox::query()->where('address', $address)->exists()) {
            throw ValidationException::withMessages(['local_part' => 'Cette adresse existe déjà dans T.Link.']);
        }

        $entity = $reach[$data['entity']];
        $password = $data['mode'] === 'ovh' ? OvhApi::generatePassword() : $data['password'];
        $provisioned = false;

        if ($data['mode'] === 'ovh') {
            try {
                $ovh->createAccount($data['local_part'], $password, $data['display_name'] ?? $entity['label']);
                $provisioned = true;
            } catch (WebmailException $exception) {
                throw ValidationException::withMessages(['mode' => $exception->getMessage()]);
            }
        }

        $mailbox = WebmailMailbox::query()->create([
            'club_id' => $entity['club_id'],
            'scope' => $entity['scope'],
            'address' => $address,
            'display_name' => $data['display_name'] ?? null,
            'signature' => $data['signature'] ?? null,
            'password' => $password,
            'provisioned' => $provisioned,
            'created_by' => $request->user()->id,
        ]);

        $this->audit($request, 'admin.webmail.mailbox.created', $mailbox, $this->trace($mailbox) + ['provisioned' => $provisioned]);

        $note = $provisioned ? ' La boîte a été créée chez OVH (elle peut mettre quelques minutes à répondre).' : '';

        return redirect()->route('admin.webmail.mailboxes.index')->with('status', 'Boîte '.$address.' ajoutée pour '.$mailbox->entityLabel().'.'.$note);
    }

    public function edit(Request $request, WebmailMailbox $mailbox): View
    {
        $this->authorizeMailbox($request, $mailbox);

        return view('admin.webmail.mailboxes.form', $this->formData($request, $mailbox));
    }

    public function update(Request $request, WebmailMailbox $mailbox): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox);

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'signature' => ['nullable', 'string', 'max:1500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $mailbox->update(['display_name' => $data['display_name'] ?? null, 'signature' => $data['signature'] ?? null, 'is_active' => $request->boolean('is_active')]);

        $this->audit($request, 'admin.webmail.mailbox.updated', $mailbox, $this->trace($mailbox) + ['is_active' => $mailbox->is_active]);

        return redirect()->route('admin.webmail.mailboxes.index')->with('status', 'Boîte '.$mailbox->address.' mise à jour.');
    }

    /** Génère un nouveau mot de passe chez OVH (boîtes créées par T.Link) ou enregistre celui qu'on fournit. */
    public function password(Request $request, WebmailMailbox $mailbox, OvhApi $ovh): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox);

        $data = $request->validate(['password' => ['nullable', 'string', 'min:8', 'max:128']]);

        if ($mailbox->provisioned) {
            $password = OvhApi::generatePassword();

            try {
                $ovh->changePassword(strstr($mailbox->address, '@', true), $password);
            } catch (WebmailException $exception) {
                return back()->withErrors(['password' => $exception->getMessage()]);
            }

            $mailbox->update(['password' => $password, 'last_error' => null]);
        } else {
            if (blank($data['password'] ?? null)) {
                return back()->withErrors(['password' => 'Saisissez le nouveau mot de passe de la boîte.']);
            }

            $mailbox->update(['password' => $data['password'], 'last_error' => null]);
        }

        $this->audit($request, 'admin.webmail.mailbox.password_reset', $mailbox, $this->trace($mailbox) + ['provisioned' => $mailbox->provisioned]);

        return redirect()->route('admin.webmail.mailboxes.edit', $mailbox)->with('status', 'Mot de passe mis à jour : T.Link le garde chiffré, personne n’a besoin de le connaître.');
    }

    public function destroy(Request $request, WebmailMailbox $mailbox, OvhApi $ovh): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox);

        $deletedAtOvh = false;

        if ($request->boolean('ovh') && $mailbox->provisioned) {
            try {
                $ovh->deleteAccount(strstr($mailbox->address, '@', true));
                $deletedAtOvh = true;
            } catch (WebmailException $exception) {
                return back()->withErrors(['ovh' => $exception->getMessage()]);
            }
        }

        $this->audit($request, 'admin.webmail.mailbox.deleted', $mailbox, $this->trace($mailbox) + ['deleted_at_ovh' => $deletedAtOvh]);
        $address = $mailbox->address;
        $mailbox->delete();

        return redirect()->route('admin.webmail.mailboxes.index')->with('status', 'Boîte '.$address.' retirée de T.Link'.($deletedAtOvh ? ' et supprimée chez OVH.' : ' (elle existe toujours chez OVH).'));
    }

    /** Teste la connexion IMAP de la boîte. */
    public function check(Request $request, WebmailMailbox $mailbox, GatewayFactory $gateways): RedirectResponse
    {
        $this->authorizeMailbox($request, $mailbox);

        try {
            $gateways->make($mailbox)->ping();
        } catch (WebmailException $exception) {
            $mailbox->update(['last_error' => mb_substr($exception->getMessage(), 0, 250)]);

            return back()->withErrors(['check' => $exception->getMessage()]);
        }

        $mailbox->update(['last_error' => null]);

        return back()->with('status', 'La boîte '.$mailbox->address.' répond.');
    }

    private function authorizeMailbox(Request $request, WebmailMailbox $mailbox): void
    {
        $this->requireModuleAction($request, self::MODULE, $request->isMethod('DELETE') ? 'delete' : 'update');

        abort_unless(MarketingEntities::allows($request->user(), $mailbox->club_id, $mailbox->scope), 403, 'Cet élément est hors de votre périmètre.');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, WebmailMailbox $mailbox): array
    {
        return [
            'mailbox' => $mailbox,
            'entityOptions' => MarketingEntities::grouped(MarketingEntities::reach($request->user())),
            'domain' => WebmailSettings::domain(),
            'apiReady' => WebmailSettings::apiReady(),
            'authorized' => $mailbox->exists ? WebmailAccess::authorized($mailbox) : collect(),
        ];
    }

    /** Aucun secret dans le journal d'audit. */
    private function trace(WebmailMailbox $mailbox): array
    {
        return ['address' => $mailbox->address, 'entity' => $mailbox->entityKey()];
    }
}
