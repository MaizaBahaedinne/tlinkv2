@extends('admin.layouts.app')

@section('title', 'Boîtes e-mail des entités')
@section('section', 'Webmail')

@section('content')
    <x-admin.page-header title="Boîtes e-mail des entités" subtitle="Une adresse partagée par club, chapitre, projet ou pour la Fondation. T.Link en garde le mot de passe (chiffré) : les membres autorisés lisent la boîte depuis le webmail, sans jamais le connaître.">
        <x-slot:actions>
            @if($canCreate)
                <a href="{{ route('admin.webmail.mailboxes.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvelle boîte</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @foreach(['check', 'ovh', 'password'] as $key)
        @if($errors->has($key))
            <div class="alert alert-error" role="alert" style="margin-bottom:12px"><x-admin.icon name="x" /> <span>{{ $errors->first($key) }}</span></div>
        @endif
    @endforeach

    @if($domain === '')
        <div class="alert alert-info" role="status" style="margin-bottom:16px"><x-admin.icon name="flag" /> <span>Le domaine des adresses n’est pas encore renseigné (menu « Connexion OVH »).</span></div>
    @endif

    <article class="card card-flush">
        @if($mailboxes->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Adresse</th><th>Entité</th><th class="hide-mobile">Origine</th><th class="hide-mobile">Dernière relève</th><th>État</th><th></th></tr></thead>
                    <tbody>
                        @foreach($mailboxes as $mailbox)
                            <tr>
                                <td><span class="cell-main">{{ $mailbox->address }}</span>@if($mailbox->display_name)<div class="cell-sub">{{ $mailbox->display_name }}</div>@endif</td>
                                <td>{{ $mailbox->entityLabel() }}</td>
                                <td class="hide-mobile">{{ $mailbox->provisioned ? 'Créée par T.Link' : 'Boîte existante' }}</td>
                                <td class="hide-mobile">{{ $mailbox->checked_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>
                                    @if($mailbox->last_error)
                                        <span class="badge badge-danger" title="{{ $mailbox->last_error }}">Erreur</span>
                                    @elseif(! $mailbox->is_active)
                                        <span class="badge badge-neutral">Désactivée</span>
                                    @else
                                        <span class="badge badge-success">Active</span>
                                    @endif
                                </td>
                                <td class="cell-actions" style="display:flex;gap:6px;justify-content:flex-end">
                                    @if($canUpdate)
                                        <a href="{{ route('admin.webmail.mailboxes.edit', $mailbox) }}" class="btn btn-secondary btn-sm">Modifier</a>
                                    @endif
                                    @if($canDelete)
                                        <x-admin.delete-button :action="route('admin.webmail.mailboxes.destroy', $mailbox)" title="Retirer cette boîte ?" message="La boîte est retirée de T.Link ; elle existe toujours chez OVH. Pour la supprimer aussi chez OVH, ouvrez sa fiche." />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">{{ $mailboxes->links() }}</div>
        @else
            <x-admin.empty icon="mail" title="Aucune boîte" text="Créez la boîte e-mail d’une entité pour que ses responsables puissent la lire depuis T.Link." />
        @endif
    </article>
@endsection
