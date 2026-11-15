@extends('admin.layouts.app')

@section('title', 'Réglages de la boîte')
@section('section', 'Webmail')

@section('content')
    <x-admin.page-header :title="'Réglages — '.$mailbox->address" subtitle="Signature, étiquettes et contacts de la boîte, partagés par tous ceux qui la gèrent.">
        <x-slot:actions>
            <a href="{{ route('admin.webmail.mail.index', ['boite' => $mailbox->id]) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à la boîte</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="stack">
        <form method="post" action="{{ route('admin.webmail.mail.signature', $mailbox) }}" class="card">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="form-grid">
                    <x-admin.textarea name="signature" label="Signature (ajoutée à chaque message envoyé)" :value="$mailbox->signature" rows="4" maxlength="1500" />
                </div>
            </div>
            @if($canUpdate)
                <div class="form-actions"><button class="btn btn-primary" type="submit"><x-admin.icon name="check" :size="16" /> Enregistrer la signature</button></div>
            @endif
        </form>

        <article class="card">
            <div class="card-body stack">
                <h3 style="margin:0">Étiquettes</h3>
                <div class="chips">
                    @forelse($labels as $label)
                        <span class="chip" style="display:inline-flex;gap:8px;align-items:center">{{ $label->name }}
                            @if($canDelete)
                                <form method="post" action="{{ route('admin.webmail.mail.labels.destroy', ['mailbox' => $mailbox, 'label' => $label]) }}" data-confirm="L’étiquette est retirée de tous les messages." data-confirm-title="Supprimer cette étiquette ?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-sm" aria-label="Supprimer l’étiquette {{ $label->name }}"><x-admin.icon name="x" :size="12" /></button>
                                </form>
                            @endif
                        </span>
                    @empty
                        <span class="text-muted">Aucune étiquette.</span>
                    @endforelse
                </div>
                @if($canUpdate)
                    <form method="post" action="{{ route('admin.webmail.mail.labels.store', $mailbox) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                        @csrf
                        <div class="field"><label for="label-name">Nouvelle étiquette</label><input id="label-name" name="name" maxlength="40" required value="{{ old('name') }}">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                        <div class="field"><label for="label-color">Couleur</label><select id="label-color" name="color">@foreach($colors as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select></div>
                        <button class="btn btn-secondary" type="submit"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
                    </form>
                @endif
            </div>
        </article>

        <article class="card card-flush">
            <div class="card-body stack">
                <h3 style="margin:0">Contacts</h3>
                <p class="text-muted text-sm" style="margin:0">Les destinataires des messages envoyés sont ajoutés automatiquement.</p>
                @if($canUpdate)
                    <form method="post" action="{{ route('admin.webmail.mail.contacts.store', $mailbox) }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                        @csrf
                        <div class="field"><label for="contact-name">Nom</label><input id="contact-name" name="name" maxlength="120"></div>
                        <div class="field"><label for="contact-email">Adresse e-mail</label><input id="contact-email" type="email" name="email" maxlength="190" required>@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                        <button class="btn btn-secondary" type="submit"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
                    </form>
                @endif
            </div>
            @if($contacts->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Nom</th><th>Adresse</th><th></th></tr></thead>
                        <tbody>
                            @foreach($contacts as $contact)
                                <tr>
                                    <td>{{ $contact->name ?: '—' }}</td>
                                    <td>{{ $contact->email }}</td>
                                    <td class="cell-actions">@if($canDelete)<x-admin.delete-button :action="route('admin.webmail.mail.contacts.destroy', ['mailbox' => $mailbox, 'contact' => $contact])" title="Supprimer ce contact ?" message="Le contact est retiré du carnet de la boîte." />@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body">{{ $contacts->links() }}</div>
            @endif
        </article>
    </div>
@endsection
