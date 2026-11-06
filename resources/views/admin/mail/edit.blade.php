@extends('admin.layouts.app')

@section('title', 'Messagerie')
@section('section', 'Messagerie')

@section('content')
    <x-admin.page-header title="Messagerie (e-mails envoyés)" subtitle="Adresse d’envoi de chaque application : confirmation d’e-mail, notifications, messages automatiques. Chaque application a sa propre boîte ; tant qu’une application n’en a pas, elle envoie avec celle de T.Link." />

    @if(! $profiles['tlink']['usable'])
        <div class="alert alert-error" role="status"><x-admin.icon name="flag" /> <span>T.Link n’a pas encore de boîte d’envoi active : les e-mails utilisent la configuration du serveur (mode « {{ $envMailer }} »). Avec « log », rien n’est envoyé et les membres ne reçoivent pas leur e-mail de confirmation.</span></div>
    @endif

    @if($errors->has('test'))
        <div class="alert alert-error" role="alert"><x-admin.icon name="x" /> <span>{{ $errors->first('test') }}</span></div>
    @endif

    <p class="text-sm text-muted" style="margin:0 0 16px">
        Boîte OVH (MX Plan) : serveur <strong>{{ $ovhHost }}</strong>, port <strong>465</strong> (SSL) ou <strong>587</strong> (STARTTLS), identifiant = l’adresse e-mail complète, mot de passe = celui de la boîte.
    </p>

    @foreach($profiles as $key => $profile)
        @php
            $data = $profile['data'];
            $open = request('open') === $key || ($key === 'tlink' && request('open') === null);
            $form = old('_profile') === $key;
            $val = fn (string $field) => $form ? old($field, $data[$field]) : $data[$field];
        @endphp
        <details class="card card-flush" style="margin-bottom:16px" @if($open || $form) open @endif>
            <summary class="card-body" style="cursor:pointer;display:flex;gap:12px;align-items:center;justify-content:space-between">
                <strong>{{ $profile['label'] }}</strong>
                <span>
                    @if($profile['usable'])
                        <span class="badge badge-success">Active · {{ $data['from_address'] }}</span>
                    @elseif($key === 'tlink')
                        <span class="badge badge-warning">Non configurée</span>
                    @else
                        <span class="badge badge-neutral">Utilise la boîte de T.Link</span>
                    @endif
                </span>
            </summary>

            <form method="post" action="{{ route('admin.mail.update', $key) }}" autocomplete="off">
                @csrf
                @method('PUT')
                <input type="hidden" name="_profile" value="{{ $key }}">

                <div class="card-body" style="border-top:1px solid var(--line)">
                    <label class="check" style="margin-bottom:16px">
                        <input type="checkbox" name="enabled" value="1" @checked($form ? old('enabled') : $data['enabled'])>
                        Envoyer les e-mails de {{ $profile['label'] }} avec cette boîte
                    </label>

                    @php
                        $fields = [
                            ['from_address', 'Adresse d’expédition', 'email', 'no-reply@votre-domaine.org', 190],
                            ['from_name', 'Nom affiché', 'text', $profile['label'].' · Fondation Tunivisions', 120],
                            ['host', 'Serveur SMTP', 'text', $ovhHost, 190],
                            ['port', 'Port', 'number', '', null],
                            ['username', 'Identifiant (adresse e-mail complète)', 'text', 'no-reply@votre-domaine.org', 190],
                        ];
                    @endphp
                    <div class="form-grid">
                        @foreach($fields as [$name, $label, $type, $placeholder, $max])
                            <div class="field {{ $form && $errors->has($name) ? 'has-error' : '' }}">
                                <label for="f-{{ $name }}-{{ $key }}">{{ $label }}</label>
                                <input id="f-{{ $name }}-{{ $key }}" type="{{ $type }}" name="{{ $name }}" value="{{ $val($name) }}" placeholder="{{ $placeholder }}" @if($max) maxlength="{{ $max }}" @endif @if($name !== 'from_name') required @endif>
                                @if($form && $errors->has($name))<p class="error">{{ $errors->first($name) }}</p>@endif
                            </div>
                        @endforeach
                        <div class="field">
                            <label for="f-encryption-{{ $key }}">Mode de connexion</label>
                            <select id="f-encryption-{{ $key }}" name="encryption">
                                @foreach($encryptions as $code => $label)
                                    <option value="{{ $code }}" @selected($val('encryption') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="hint">SSL avec le port 465, ou STARTTLS avec le port 587.</p>
                        </div>
                        <div class="field span-2 {{ $form && $errors->has('password') ? 'has-error' : '' }}">
                            <label for="f-password-{{ $key }}">Mot de passe de la boîte</label>
                            <input id="f-password-{{ $key }}" type="password" name="password" value="" autocomplete="new-password" maxlength="255" placeholder="{{ $profile['hasPassword'] ? '•••••••• (enregistré : laissez vide pour le conserver)' : '' }}">
                            <p class="hint">Chiffré avant d’être enregistré, jamais ré-affiché.</p>
                            @if($form && $errors->has('password'))<p class="error">{{ $errors->first('password') }}</p>@endif
                        </div>
                    </div>
                </div>
                <div class="card-body" style="border-top:1px solid var(--line)">
                    <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
                </div>
            </form>

            <form method="post" action="{{ route('admin.mail.test', $key) }}" class="card-body" style="border-top:1px solid var(--line)">
                @csrf
                <label for="f-to-{{ $key }}" class="text-sm"><strong>Tester l’envoi</strong> (avec la configuration enregistrée)</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
                    <input id="f-to-{{ $key }}" type="email" name="to" required maxlength="190" placeholder="votre.adresse@exemple.com" value="{{ old('to', auth()->user()->email) }}" style="max-width:22rem">
                    <button type="submit" class="btn btn-secondary">Envoyer un message de test</button>
                </div>
            </form>
        </details>
    @endforeach
@endsection
