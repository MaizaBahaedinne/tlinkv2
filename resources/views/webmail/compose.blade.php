@extends('layouts.base', ['title' => 'Écrire un message'])

@section('content')
@php
    $btnPrimary = 'inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90';
    $btnSecondary = 'inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50';
    $input = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-tlink-blue focus:outline-none';
@endphp
<div class="mg-shell bg-tlink-sand text-slate-800">
    @include('partials.app-header')

    <main class="min-h-0 overflow-y-auto">
    <div class="app-container max-w-3xl space-y-4 py-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold text-slate-900">Écrire un message</h1>
                <p class="text-sm text-slate-500">Depuis {{ $mailbox->address }}{{ $mailbox->signature ? ' — votre signature est ajoutée à l’envoi.' : '' }}</p>
            </div>
            <a href="{{ route('webmail.index', ['boite' => $mailbox->id]) }}" class="{{ $btnSecondary }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour à la boîte</a>
        </div>

        @include('webmail._flash')

        <form method="post" action="{{ route('webmail.send', $mailbox) }}" enctype="multipart/form-data" autocomplete="off" class="space-y-4 rounded-2xl bg-white p-5 shadow-soft">
            @csrf
            <input type="hidden" name="in_reply_to" value="{{ old('in_reply_to', $draft['in_reply_to']) }}">
            <input type="hidden" name="references" value="{{ old('references', $draft['references']) }}">
            <input type="hidden" name="draft_uid" value="{{ old('draft_uid', $draft['draft_uid']) }}">
            <input type="hidden" name="reply_uid" value="{{ old('reply_uid', $draft['reply_uid']) }}">
            <input type="hidden" name="reply_folder" value="{{ old('reply_folder', $draft['reply_folder']) }}">

            <div>
                <label for="f-to" class="mb-1 block text-sm font-medium text-slate-700">À (séparez les adresses par une virgule)</label>
                <input id="f-to" name="to" value="{{ old('to', $draft['to']) }}" maxlength="2000" autocomplete="off" class="{{ $input }}">
                @error('to')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>
            @if($contacts->count())
                <div>
                    <p class="mb-1.5 text-xs text-slate-500">Contacts : cliquez pour ajouter</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($contacts as $contact)
                            <button type="button" class="rounded-full border border-slate-200 px-2.5 py-0.5 text-xs text-slate-600 hover:bg-slate-50" data-add-recipient="{{ $contact->email }}" title="{{ $contact->email }}">{{ $contact->name ?: $contact->email }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="f-cc" class="mb-1 block text-sm font-medium text-slate-700">Cc</label>
                    <input id="f-cc" name="cc" value="{{ old('cc', $draft['cc']) }}" maxlength="2000" class="{{ $input }}">
                    @error('cc')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="f-bcc" class="mb-1 block text-sm font-medium text-slate-700">Cci</label>
                    <input id="f-bcc" name="bcc" value="{{ old('bcc') }}" maxlength="2000" class="{{ $input }}">
                    @error('bcc')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="f-subject" class="mb-1 block text-sm font-medium text-slate-700">Objet</label>
                <input id="f-subject" name="subject" value="{{ old('subject', $draft['subject']) }}" maxlength="250" class="{{ $input }}">
            </div>
            <div>
                <label for="f-body" class="mb-1 block text-sm font-medium text-slate-700">Message</label>
                <textarea id="f-body" name="body" rows="14" maxlength="100000" class="{{ $input }}">{{ old('body', $draft['body']) }}</textarea>
            </div>
            <div>
                <label for="f-attachments" class="mb-1 block text-sm font-medium text-slate-700">Pièces jointes (10 Mo chacune, 25 Mo au total)</label>
                <input id="f-attachments" type="file" name="attachments[]" multiple class="block w-full text-sm text-slate-600">
                @if($mode === 'forward')<p class="mt-1 text-xs text-slate-500">Les pièces jointes du message d’origine ne sont pas transférées : ajoutez-les à nouveau si besoin.</p>@endif
                @error('attachments')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                @error('attachments.*')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                <button type="submit" name="action" value="send" class="{{ $btnPrimary }}"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Envoyer</button>
                <button type="submit" name="action" value="draft" class="{{ $btnSecondary }}">Enregistrer en brouillon</button>
            </div>
        </form>
    </div>
    </main>
</div>

<script>
    document.querySelectorAll('[data-add-recipient]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = document.getElementById('f-to');
            var current = field.value.trim().replace(/[,;\s]+$/, '');
            field.value = (current ? current + ', ' : '') + button.dataset.addRecipient;
            field.focus();
        });
    });
</script>
@endsection
