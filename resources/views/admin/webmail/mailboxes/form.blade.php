@extends('admin.layouts.app')

@section('title', $mailbox->exists ? 'Modifier une boîte e-mail' : 'Nouvelle boîte e-mail')
@section('section', 'Webmail')

@section('content')
    <x-admin.page-header :title="$mailbox->exists ? $mailbox->address : 'Nouvelle boîte e-mail'" subtitle="Le mot de passe est géré par T.Link : généré, chiffré et jamais affiché.">
        <x-slot:actions>
            @if($mailbox->exists && $mailbox->is_active)
                <a href="{{ \App\Support\Domains::url('webmail', route('webmail.index', ['boite' => $mailbox->id], false)) }}" class="btn btn-secondary" target="_blank" rel="noopener"><x-admin.icon name="mail" :size="16" /> Ouvrir dans T.Link Webmail</a>
            @endif
            <a href="{{ route('admin.webmail.mailboxes.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux boîtes</a>
        </x-slot:actions>
    </x-admin.page-header>

    @foreach(['mode', 'password', 'ovh'] as $key)
        @if($errors->has($key) && $mailbox->exists === ($key !== 'mode'))
            <div class="alert alert-error" role="alert" style="margin-bottom:12px"><x-admin.icon name="x" /> <span>{{ $errors->first($key) }}</span></div>
        @endif
    @endforeach

    <form method="post" action="{{ $mailbox->exists ? route('admin.webmail.mailboxes.update', $mailbox) : route('admin.webmail.mailboxes.store') }}" class="card" autocomplete="off">
        @csrf
        @if($mailbox->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="form-grid">
                @if($mailbox->exists)
                    <div class="field"><label>Entité</label><p style="margin:0">{{ $mailbox->entityLabel() }}</p></div>
                    <div class="field"><label>Origine</label><p style="margin:0">{{ $mailbox->provisioned ? 'Créée par T.Link chez OVH' : 'Boîte existante enregistrée avec son mot de passe' }}</p></div>
                @else
                    <x-admin.entity-select name="entity" label="Entité (Fondation, chapitre, projet ou club)" :groups="$entityOptions" :value="old('entity')" :required="true" />
                    <x-admin.input name="local_part" label="Adresse{{ $domain ? ' (avant @'.$domain.')' : '' }}" :required="true" maxlength="64" placeholder="contact" />
                @endif
                <x-admin.input name="display_name" label="Nom affiché (facultatif)" :value="$mailbox->display_name" maxlength="120" hint="Nom de l’expéditeur et titre de la boîte ; l’entité par défaut." />
                <div class="field">
                    <label>État</label>
                    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $mailbox->is_active ?? true))> Boîte active (relevée et visible dans le webmail)</label>
                </div>
                <div class="field span-2 {{ $errors->has('signature') ? 'has-error' : '' }}">
                    <label for="f-signature">Signature (ajoutée à chaque message envoyé)</label>
                    <textarea id="f-signature" name="signature" rows="10" maxlength="{{ \App\Support\Webmail\WebmailSignature::MAX_LENGTH }}" spellcheck="false" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px">{{ old('signature', $mailbox->signature) }}</textarea>
                    <p class="hint">Texte libre (long texte accepté) ou code HTML : liens, mise en forme, tableau, logo en https. Les scripts et formulaires sont retirés à l’enregistrement.</p>
                    @error('signature')<p class="error">{{ $message }}</p>@enderror
                    <div class="text-muted text-sm" style="margin:10px 0 4px;font-weight:700">Aperçu</div>
                    <iframe id="signature-preview" sandbox="" title="Aperçu de la signature" style="width:100%;height:180px;border:1px solid var(--line);border-radius:8px;background:#fff"></iframe>
                </div>

                @unless($mailbox->exists)
                    <div class="field span-2">
                        <label>Création de la boîte</label>
                        <label class="check"><input type="radio" name="mode" value="ovh" @checked(old('mode', $apiReady ? 'ovh' : 'existing') === 'ovh') @disabled(! $apiReady)> Créer la boîte chez OVH (T.Link génère le mot de passe)@unless($apiReady) <span class="text-muted">— API OVH non configurée</span>@endunless</label>
                        <label class="check" style="margin-top:8px"><input type="radio" name="mode" value="existing" @checked(old('mode', $apiReady ? 'ovh' : 'existing') === 'existing')> Enregistrer une boîte déjà créée chez OVH</label>
                    </div>
                    <x-admin.input name="password" type="password" label="Mot de passe de la boîte déjà créée" maxlength="128" autocomplete="new-password" hint="Seulement pour une boîte existante : il est chiffré et ne sera plus jamais affiché." />
                @endunless
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>

    @if($mailbox->exists)
        <article class="card" style="margin-top:18px">
            <div class="card-body stack">
                <h3 style="margin:0">Comptes autorisés à utiliser cette boîte</h3>
                <p class="text-muted" style="margin:0">Cherchez un compte pour l’autoriser, puis réglez ses droits. Les titulaires d’un poste dont la matrice ouvre « Lecture et envoi » (Gouvernance &gt; Modules) y ont aussi accès : leurs droits se règlent dans la matrice. Chacun utilise la boîte dans T.Link Webmail.</p>

                <form method="post" action="{{ route('admin.webmail.mailboxes.users.store', $mailbox) }}" id="grant-form" autocomplete="off" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
                    @csrf
                    <input type="hidden" name="user_id" id="grant-user-id" value="">
                    <div class="field" style="min-width:300px;flex:1;position:relative">
                        <label for="grant-search">Rechercher un compte (nom ou e-mail)</label>
                        <input id="grant-search" type="search" placeholder="Tapez au moins 2 lettres…" role="combobox" aria-expanded="false" aria-controls="grant-results" aria-autocomplete="list" data-url="{{ route('admin.webmail.mailboxes.users.search', $mailbox) }}">
                        <div id="grant-results" role="listbox" hidden style="position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);max-height:260px;overflow:auto"></div>
                        <p class="hint" id="grant-picked" hidden></p>
                    </div>
                    <div class="field">
                        <label>Droits</label>
                        <span class="check" style="margin-right:12px"><input type="checkbox" checked disabled> Lire</span>
                        @foreach(array_slice(\App\Support\Webmail\WebmailAccess::ACTIONS, 1, null, true) as $action => $label)
                            <label class="check" style="display:inline-flex;margin-right:12px"><input type="checkbox" name="actions[]" value="{{ $action }}" checked> {{ $label }}</label>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-primary" id="grant-submit" disabled><x-admin.icon name="plus" :size="16" /> Autoriser</button>
                </form>
                @error('user_id')<p class="error" style="margin:0">{{ $message }}</p>@enderror

                @if($authorized->isEmpty())
                    <p style="margin:0">Aucun compte n’est autorisé pour le moment.</p>
                @else
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>Compte</th><th>Origine</th><th>Droits</th><th></th></tr></thead>
                            <tbody>
                                @foreach($authorized as $row)
                                    <tr>
                                        <td><strong>{{ $row['user']->name }}</strong><br><span class="text-muted">{{ str_ends_with((string) $row['user']->email, '@legacy.invalid') ? '' : $row['user']->email }}</span></td>
                                        <td>
                                            @if($row['manual'])<span class="badge badge-success">Choisi ici</span>@endif
                                            @if($row['roles'])<div class="text-muted text-sm">Poste : {{ implode(' · ', $row['roles']) }}</div>@endif
                                        </td>
                                        <td>
                                            @if($row['manual'])
                                                <form method="post" action="{{ route('admin.webmail.mailboxes.users.update', ['mailbox' => $mailbox, 'user' => $row['user']->id]) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                                                    @csrf
                                                    @method('PUT')
                                                    <span class="check"><input type="checkbox" checked disabled> Lire</span>
                                                    @foreach(array_slice(\App\Support\Webmail\WebmailAccess::ACTIONS, 1, null, true) as $action => $label)
                                                        <label class="check"><input type="checkbox" name="actions[]" value="{{ $action }}" @checked(in_array($action, $row['manual_actions'], true))> {{ $label }}</label>
                                                    @endforeach
                                                    <button class="btn btn-secondary btn-sm" type="submit">Enregistrer</button>
                                                </form>
                                            @else
                                                @foreach($row['actions'] as $action)<span class="badge badge-neutral">{{ \App\Support\Webmail\WebmailAccess::ACTIONS[$action] }}</span> @endforeach
                                            @endif
                                        </td>
                                        <td class="cell-actions">
                                            @if($row['manual'])
                                                <form method="post" action="{{ route('admin.webmail.mailboxes.users.destroy', ['mailbox' => $mailbox, 'user' => $row['user']->id]) }}" data-confirm="Ce compte ne pourra plus utiliser la boîte (sauf par son poste)." data-confirm-title="Retirer l’accès de {{ $row['user']->name }} ?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-danger btn-sm" type="submit">Retirer</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </article>

        <article class="card" style="margin-top:18px">
            <div class="card-body stack">
                <h3 style="margin:0">Mot de passe et connexion</h3>
                @if($mailbox->last_error)
                    <div class="alert alert-error" role="alert"><x-admin.icon name="x" /> <span>{{ $mailbox->last_error }}</span></div>
                @endif
                <form method="post" action="{{ route('admin.webmail.mailboxes.password', $mailbox) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end" autocomplete="off">
                    @csrf
                    @unless($mailbox->provisioned)
                        <div class="field" style="min-width:260px"><label for="f-new-password">Nouveau mot de passe de la boîte</label><input id="f-new-password" type="password" name="password" minlength="8" maxlength="128" autocomplete="new-password"></div>
                    @endunless
                    <button class="btn btn-secondary" type="submit"><x-admin.icon name="key" :size="16" /> {{ $mailbox->provisioned ? 'Générer un nouveau mot de passe' : 'Enregistrer le mot de passe' }}</button>
                </form>
                <form method="post" action="{{ route('admin.webmail.mailboxes.check', $mailbox) }}">
                    @csrf
                    <button class="btn btn-secondary" type="submit"><x-admin.icon name="shield" :size="16" /> Tester la connexion de la boîte</button>
                </form>
                @if($mailbox->provisioned)
                    <form method="post" action="{{ route('admin.webmail.mailboxes.destroy', $mailbox) }}" data-confirm="La boîte et tous ses messages seront supprimés chez OVH. Cette action est définitive." data-confirm-title="Supprimer aussi chez OVH ?">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="ovh" value="1">
                        <button class="btn btn-danger" type="submit"><x-admin.icon name="trash" :size="16" /> Supprimer la boîte chez OVH et dans T.Link</button>
                    </form>
                @endif
            </div>
        </article>
    @endif

    <script>
        (function () {
            var field = document.getElementById('f-signature');
            var frame = document.getElementById('signature-preview');
            if (field && frame) {
                var render = function () {
                    var value = field.value.trim();
                    var isHtml = /<\s*\/?\s*[a-z][a-z0-9]*(\s[^>]*)?>/i.test(value);
                    var body = isHtml ? value : value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                    frame.srcdoc = '<!doctype html><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src https: data:; style-src \'unsafe-inline\'"><body style="margin:10px;font:14px/1.5 Arial,sans-serif;color:#222">' + (body || '<em style="color:#999">Aucune signature</em>') + '</body>';
                };
                field.addEventListener('input', render);
                render();
            }

            var search = document.getElementById('grant-search');
            if (!search) { return; }
            var results = document.getElementById('grant-results');
            var picked = document.getElementById('grant-picked');
            var userId = document.getElementById('grant-user-id');
            var submit = document.getElementById('grant-submit');
            var timer = null;

            var reset = function () { userId.value = ''; submit.disabled = true; picked.hidden = true; };
            var close = function () { results.hidden = true; search.setAttribute('aria-expanded', 'false'); };

            search.addEventListener('input', function () {
                reset();
                clearTimeout(timer);
                var term = search.value.trim();
                if (term.length < 2) { close(); return; }
                timer = setTimeout(function () {
                    fetch(search.dataset.url + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (response) { return response.ok ? response.json() : { users: [] }; })
                        .then(function (data) {
                            results.innerHTML = '';
                            if (!data.users.length) {
                                var none = document.createElement('div');
                                none.textContent = 'Aucun compte trouvé.';
                                none.style.cssText = 'padding:10px 12px;color:#777';
                                results.appendChild(none);
                            }
                            data.users.forEach(function (user) {
                                var option = document.createElement('button');
                                option.type = 'button';
                                option.setAttribute('role', 'option');
                                option.style.cssText = 'display:block;width:100%;text-align:left;padding:8px 12px;border:0;background:none;cursor:pointer';
                                var name = document.createElement('strong');
                                name.textContent = user.name;
                                option.appendChild(name);
                                if (user.email) {
                                    var mail = document.createElement('span');
                                    mail.textContent = ' — ' + user.email;
                                    mail.style.color = '#777';
                                    option.appendChild(mail);
                                }
                                option.addEventListener('click', function () {
                                    userId.value = user.id;
                                    search.value = user.name;
                                    picked.textContent = 'Compte choisi : ' + user.name + (user.email ? ' (' + user.email + ')' : '');
                                    picked.hidden = false;
                                    submit.disabled = false;
                                    close();
                                });
                                results.appendChild(option);
                            });
                            results.hidden = false;
                            search.setAttribute('aria-expanded', 'true');
                        })
                        .catch(close);
                }, 250);
            });

            document.addEventListener('click', function (event) { if (!results.contains(event.target) && event.target !== search) { close(); } });
            search.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); } });
        })();
    </script>
@endsection
