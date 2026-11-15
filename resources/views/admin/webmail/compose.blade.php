@extends('admin.layouts.app')

@section('title', 'Écrire un message')
@section('section', 'Webmail')

@section('content')
    <x-admin.page-header title="Écrire un message" :subtitle="'Depuis '.$mailbox->address.($mailbox->signature ? ' — votre signature est ajoutée à l’envoi.' : '')">
        <x-slot:actions>
            <a href="{{ route('admin.webmail.mail.index', ['boite' => $mailbox->id]) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à la boîte</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->has('send'))
        <div class="alert alert-error" role="alert" style="margin-bottom:12px"><x-admin.icon name="x" /> <span>{{ $errors->first('send') }}</span></div>
    @endif

    <form method="post" action="{{ route('admin.webmail.mail.send', $mailbox) }}" enctype="multipart/form-data" class="card" autocomplete="off">
        @csrf
        <input type="hidden" name="in_reply_to" value="{{ old('in_reply_to', $draft['in_reply_to']) }}">
        <input type="hidden" name="references" value="{{ old('references', $draft['references']) }}">
        <input type="hidden" name="draft_uid" value="{{ old('draft_uid', $draft['draft_uid']) }}">
        <input type="hidden" name="reply_uid" value="{{ old('reply_uid', $draft['reply_uid']) }}">
        <input type="hidden" name="reply_folder" value="{{ old('reply_folder', $draft['reply_folder']) }}">

        <div class="card-body">
            <div class="form-grid">
                <x-admin.input name="to" label="À (séparez les adresses par une virgule)" :value="$draft['to']" :span="true" maxlength="2000" autocomplete="off" />
                @if($contacts->count())
                    <div class="field span-2">
                        <div class="text-muted text-sm" style="margin-bottom:6px">Contacts : cliquez pour ajouter</div>
                        <div class="chips">
                            @foreach($contacts as $contact)
                                <button type="button" class="chip" data-add-recipient="{{ $contact->email }}" title="{{ $contact->email }}">{{ $contact->name ?: $contact->email }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
                <x-admin.input name="cc" label="Cc" :value="$draft['cc']" maxlength="2000" />
                <x-admin.input name="bcc" label="Cci" maxlength="2000" />
                <x-admin.input name="subject" label="Objet" :value="$draft['subject']" :span="true" maxlength="250" />
                <x-admin.textarea name="body" label="Message" :value="$draft['body']" rows="14" maxlength="100000" />
                <div class="field span-2 {{ $errors->has('attachments') || $errors->has('attachments.*') ? 'has-error' : '' }}">
                    <label for="f-attachments">Pièces jointes (10 Mo chacune, 25 Mo au total)</label>
                    <input id="f-attachments" type="file" name="attachments[]" multiple>
                    @if($mode === 'forward')<p class="hint">Les pièces jointes du message d’origine ne sont pas transférées : ajoutez-les à nouveau si besoin.</p>@endif
                    @error('attachments')<p class="error">{{ $message }}</p>@enderror
                    @error('attachments.*')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
        <div class="form-actions" style="display:flex;gap:10px">
            <button type="submit" name="action" value="send" class="btn btn-primary"><x-admin.icon name="send" :size="16" /> Envoyer</button>
            <button type="submit" name="action" value="draft" class="btn btn-secondary">Enregistrer en brouillon</button>
        </div>
    </form>

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
