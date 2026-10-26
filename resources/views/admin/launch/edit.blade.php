@extends('admin.layouts.app')

@section('title', 'Lancement du site')
@section('section', 'Lancement du site')

@section('content')
    <x-admin.page-header title="Lancement du site" subtitle="Compte à rebours affiché à la place du site public et de T.Link. Les administrateurs globaux voient toujours le site normalement.">
        <x-slot:actions>
            <a href="{{ route('site.root') }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-admin.icon name="external" :size="16" /> Voir le site</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($closed)
        <div class="alert alert-error" role="status"><x-admin.icon name="flag" /> <span>Le site est <strong>fermé au public</strong>@if($launchAt) jusqu’au {{ $launchAt->locale('fr')->translatedFormat('l j F Y à H\hi') }}@endif. Il s’ouvrira automatiquement à cette date.</span></div>
    @elseif($settings['enabled'])
        <div class="alert alert-success" role="status"><x-admin.icon name="check" /> <span>La date d’ouverture est passée : le site est <strong>ouvert à tous</strong>. Vous pouvez désactiver le compte à rebours.</span></div>
    @else
        <div class="alert alert-success" role="status"><x-admin.icon name="check" /> <span>Le compte à rebours est désactivé : le site est <strong>ouvert à tous</strong>.</span></div>
    @endif

    <form method="post" action="{{ route('admin.launch.update') }}" class="card card-flush" style="max-width:760px">
        @csrf
        @method('PUT')

        <div class="card-body">
            <label class="check" style="margin-bottom:16px">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
                Activer le compte à rebours (le site est fermé aux visiteurs et aux membres, sauf aux administrateurs globaux)
            </label>

            <div class="form-grid">
                <div class="field span-2 {{ $errors->has('launch_at') ? 'has-error' : '' }}">
                    <label for="f-launch_at">Date et heure d’ouverture</label>
                    <input id="f-launch_at" type="datetime-local" name="launch_at" value="{{ old('launch_at', $settings['launch_at']) }}">
                    <p class="hint">Heure de {{ $timezone }}. Le site s’ouvre tout seul à cette date.</p>
                    @error('launch_at')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-admin.input name="title" label="Titre" :value="$settings['title']" :placeholder="$defaults['title']" :maxlength="120" :span="true" />
                <div class="field span-2 {{ $errors->has('message') ? 'has-error' : '' }}">
                    <label for="f-message">Message</label>
                    <textarea id="f-message" name="message" rows="4" maxlength="1000" placeholder="{{ $defaults['message'] }}">{{ old('message', $settings['message']) }}</textarea>
                    <p class="hint">Laissez vide pour utiliser les textes par défaut.</p>
                    @error('message')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            <p class="text-sm text-muted" style="margin-bottom:0">Pendant la fermeture, seule la page de connexion reste accessible (lien « Accès administrateur » en bas du compte à rebours). Connectez-vous avec votre compte administrateur global pour parcourir le site.</p>
        </div>
        <div class="card-body" style="border-top:1px solid var(--line)">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>
@endsection
