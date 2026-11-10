@extends('admin.layouts.app')

@section('title', $definition['label'])
@section('section', 'Messagerie')

@section('content')
    <x-admin.page-header :title="$definition['label']" :subtitle="$definition['description']" />

    @include('admin.mail._tabs')

    @if($errors->has('test'))
        <div class="alert alert-error" role="alert"><x-admin.icon name="x" /> <span>{{ $errors->first('test') }}</span></div>
    @endif

    <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));align-items:start">
        <article class="card card-flush">
            <form id="template-form" method="post" action="{{ route('admin.mail.templates.update', $key) }}">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-grid">
                        <div class="field span-2 {{ $errors->has('subject') ? 'has-error' : '' }}">
                            <label for="t-subject">Objet</label>
                            <input id="t-subject" type="text" name="subject" value="{{ old('subject', $values['subject']) }}" maxlength="190" required>
                            @error('subject')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field span-2 {{ $errors->has('greeting') ? 'has-error' : '' }}">
                            <label for="t-greeting">Formule d’accueil</label>
                            <input id="t-greeting" type="text" name="greeting" value="{{ old('greeting', $values['greeting']) }}" maxlength="190" required>
                            @error('greeting')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field span-2 {{ $errors->has('body') ? 'has-error' : '' }}">
                            <label for="t-body">Message</label>
                            <textarea id="t-body" name="body" rows="9" maxlength="4000" required>{{ old('body', $values['body']) }}</textarea>
                            <p class="hint">Une ligne = un paragraphe. Mettez un mot en gras avec **mot**.@if($definition['button']) Écrivez <code>{{ $placeholder }}</code> seul sur une ligne pour placer le bouton.@endif</p>
                            @error('body')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        @if($definition['button'])
                            <div class="field span-2 {{ $errors->has('button_label') ? 'has-error' : '' }}">
                                <label for="t-button">Texte du bouton</label>
                                <input id="t-button" type="text" name="button_label" value="{{ old('button_label', $values['button_label']) }}" maxlength="80" required>
                                @error('button_label')<p class="error">{{ $message }}</p>@enderror
                            </div>
                        @endif
                        <div class="field span-2 {{ $errors->has('salutation') ? 'has-error' : '' }}">
                            <label for="t-salutation">Signature</label>
                            <input id="t-salutation" type="text" name="salutation" value="{{ old('salutation', $values['salutation']) }}" maxlength="190" required>
                            @error('salutation')<p class="error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div style="margin-top:8px">
                        <strong class="text-sm">Variables disponibles</strong>
                        <ul class="text-sm text-muted" style="margin:6px 0 0;padding-left:18px">
                            @foreach($definition['variables'] as $name => $description)
                                <li><code>{{ '{'.$name.'}' }}</code> : {{ $description }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="card-body" style="border-top:1px solid var(--line);display:flex;gap:8px;flex-wrap:wrap">
                    <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
                    <button type="submit" class="btn btn-secondary" formaction="{{ route('admin.mail.templates.preview', $key) }}" formmethod="post" formtarget="mail-preview">Prévisualiser</button>
                </div>
            </form>

            <form method="post" action="{{ route('admin.mail.templates.test', $key) }}" class="card-body" style="border-top:1px solid var(--line)">
                @csrf
                <label for="t-to" class="text-sm"><strong>Envoyer un message de test</strong> (modèle enregistré, valeurs d’exemple)</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
                    <input id="t-to" type="email" name="to" required maxlength="190" value="{{ old('to', auth()->user()->email) }}" style="max-width:22rem">
                    <button type="submit" class="btn btn-secondary">Envoyer le test</button>
                </div>
                @error('to')<p class="error" style="margin-top:6px">{{ $message }}</p>@enderror
            </form>

            @if($customized)
                <form method="post" action="{{ route('admin.mail.templates.reset', $key) }}" class="card-body" style="border-top:1px solid var(--line)" onsubmit="return confirm('Rétablir le modèle d’origine ? Votre texte personnalisé sera perdu.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm">Rétablir le modèle d’origine</button>
                </form>
            @endif
        </article>

        <article class="card card-flush">
            <div class="card-body" style="border-bottom:1px solid var(--line)">
                <strong>Aperçu</strong>
                <p class="text-sm text-muted" style="margin:4px 0 0">Avec des valeurs d’exemple. « Prévisualiser » affiche le texte en cours de saisie, sans l’enregistrer.</p>
            </div>
            <iframe name="mail-preview" title="Aperçu de l’e-mail" sandbox="" srcdoc="{{ $preview }}" style="width:100%;height:640px;border:0;background:#f5f7fb"></iframe>
        </article>
    </div>
@endsection
