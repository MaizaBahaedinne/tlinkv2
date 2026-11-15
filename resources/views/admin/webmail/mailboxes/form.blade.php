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
                <x-admin.textarea name="signature" label="Signature (ajoutée à chaque message envoyé)" :value="$mailbox->signature" rows="3" maxlength="1500" />

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
                <p class="text-muted" style="margin:0">Titulaires d’un poste de l’entité dont la matrice (Gouvernance &gt; Modules, « Lecture et envoi ») ouvre le webmail. Ils l’utilisent dans l’application T.Link Webmail.</p>
                @if($authorized->isEmpty())
                    <p style="margin:0">Aucun compte n’est autorisé pour le moment.</p>
                @else
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>Compte</th><th>Poste</th><th>Droits</th></tr></thead>
                            <tbody>
                                @foreach($authorized as $row)
                                    <tr>
                                        <td><strong>{{ $row['user']->name }}</strong><br><span class="text-muted">{{ $row['user']->email }}</span></td>
                                        <td>{{ implode(' · ', $row['roles']) }}</td>
                                        <td>@foreach($row['actions'] as $action)<span class="badge badge-neutral">{{ \App\Support\Webmail\WebmailAccess::ACTIONS[$action] }}</span> @endforeach</td>
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
@endsection
