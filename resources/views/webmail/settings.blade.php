@extends('layouts.base', ['title' => 'Réglages de la boîte'])

@section('content')
@php
    $btnPrimary = 'inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90';
    $btnSecondary = 'inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50';
    $input = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-tlink-blue focus:outline-none';
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-4 py-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold text-slate-900">Réglages</h1>
                <p class="text-sm text-slate-500">{{ $mailbox->address }} — signature, étiquettes et contacts, partagés par tous ceux qui la gèrent.</p>
            </div>
            <a href="{{ route('webmail.index', ['boite' => $mailbox->id]) }}" class="{{ $btnSecondary }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à la boîte</a>
        </div>

        @include('webmail._flash')

        <form method="post" action="{{ route('webmail.signature', $mailbox) }}" class="space-y-3 rounded-2xl bg-white p-5 shadow-soft">
            @csrf
            @method('PUT')
            <label for="f-signature" class="block text-sm font-semibold text-slate-900">Signature (ajoutée à chaque message envoyé)</label>
            <textarea id="f-signature" name="signature" rows="8" maxlength="{{ \App\Support\Webmail\WebmailSignature::MAX_LENGTH }}" spellcheck="false" class="{{ $input }} font-mono">{{ old('signature', $mailbox->signature) }}</textarea>
            <p class="text-xs text-slate-500">Texte libre (long texte accepté) ou code HTML : liens, mise en forme, logo en https. Les scripts et formulaires sont retirés à l’enregistrement.</p>
            @error('signature')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror
            @if(filled($mailbox->signature))
                <p class="pt-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Aperçu</p>
                @php($previewDocument = '<!doctype html><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src https: data:; style-src \'unsafe-inline\'"><body style="margin:10px;font:14px/1.5 Arial,sans-serif;color:#222">'.\App\Support\Webmail\WebmailSignature::html($mailbox->signature).'</body>')
                <iframe sandbox="" title="Aperçu de la signature" srcdoc="{{ $previewDocument }}" class="h-40 w-full rounded-xl border border-slate-200 bg-white"></iframe>
            @endif
            @if($canUpdate)
                <button class="{{ $btnPrimary }}" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Enregistrer la signature</button>
            @endif
        </form>

        <section class="space-y-3 rounded-2xl bg-white p-5 shadow-soft">
            <h2 class="font-semibold text-slate-900">Étiquettes</h2>
            <div class="flex flex-wrap gap-2">
                @forelse($labels as $label)
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-sm">
                        {{ $label->name }}
                        @if($canDelete)
                            <form method="post" action="{{ route('webmail.labels.destroy', ['mailbox' => $mailbox, 'label' => $label]) }}" onsubmit="return confirm('Supprimer cette étiquette ? Elle est retirée de tous les messages.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-rose-700" aria-label="Supprimer l’étiquette {{ $label->name }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                            </form>
                        @endif
                    </span>
                @empty
                    <span class="text-sm text-slate-500">Aucune étiquette.</span>
                @endforelse
            </div>
            @if($canUpdate)
                <form method="post" action="{{ route('webmail.labels.store', $mailbox) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="label-name" class="mb-1 block text-xs font-medium text-slate-500">Nouvelle étiquette</label>
                        <input id="label-name" name="name" maxlength="40" required value="{{ old('name') }}" class="{{ $input }}">
                        @error('name')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="label-color" class="mb-1 block text-xs font-medium text-slate-500">Couleur</label>
                        <select id="label-color" name="color" class="{{ $input }}">@foreach($colors as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select>
                    </div>
                    <button class="{{ $btnSecondary }}" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter</button>
                </form>
            @endif
        </section>

        <section class="space-y-3 rounded-2xl bg-white p-5 shadow-soft">
            <h2 class="font-semibold text-slate-900">Contacts</h2>
            <p class="text-sm text-slate-500">Les destinataires des messages envoyés sont ajoutés automatiquement.</p>
            @if($canUpdate)
                <form method="post" action="{{ route('webmail.contacts.store', $mailbox) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="contact-name" class="mb-1 block text-xs font-medium text-slate-500">Nom</label>
                        <input id="contact-name" name="name" maxlength="120" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="contact-email" class="mb-1 block text-xs font-medium text-slate-500">Adresse e-mail</label>
                        <input id="contact-email" type="email" name="email" maxlength="190" required class="{{ $input }}">
                        @error('email')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <button class="{{ $btnSecondary }}" type="submit"><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter</button>
                </form>
            @endif
            @if($contacts->count())
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach($contacts as $contact)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <span class="min-w-0 truncate"><span class="font-medium text-slate-800">{{ $contact->name ?: '—' }}</span> <span class="text-slate-500">{{ $contact->email }}</span></span>
                            @if($canDelete)
                                <form method="post" action="{{ route('webmail.contacts.destroy', ['mailbox' => $mailbox, 'contact' => $contact]) }}" onsubmit="return confirm('Supprimer ce contact ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-rose-700" aria-label="Supprimer {{ $contact->email }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div>{{ $contacts->links() }}</div>
            @endif
        </section>
    </main>
</div>
@endsection
