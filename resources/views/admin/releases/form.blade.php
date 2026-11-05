@extends('admin.layouts.app')

@php
    $pageTitle = $release->exists ? 'Version '.$release->version : 'Nouvelle version';
    $publishedValue = old('published_at', $release->published_at?->format('Y-m-d\TH:i'));
    $lines = collect(array_keys(\App\Models\AppRelease::TYPES))->mapWithKeys(fn ($type) => [$type => implode("\n", (array) ($release->notes[$type] ?? []))])->all();
    $placeholders = [
        'new' => 'Une nouveauté par ligne',
        'improvement' => 'Une amélioration par ligne',
        'fix' => 'Une correction par ligne',
        'security' => 'Un correctif de sécurité par ligne',
    ];
@endphp
@section('title', $pageTitle)
@section('section', 'Versions et changements')

@section('content')
    <x-admin.page-header :title="$pageTitle" subtitle="Un changement par ligne dans chaque catégorie ; les catégories vides ne sont pas affichées.">
        <x-slot:actions>
            <a href="{{ route('admin.releases.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux versions</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $release->exists ? route('admin.releases.update', $release) : route('admin.releases.store') }}" class="card card-flush">
        @csrf
        @if($release->exists)
            @method('PUT')
        @endif

        <div class="card-body">
            <div class="form-grid">
                <x-admin.input name="version" label="Version" :value="$release->version" :required="true" :maxlength="40" placeholder="1.4.0" />
                <x-admin.input name="title" label="Titre" :value="$release->title" :required="true" :maxlength="160" />
                <x-admin.textarea name="summary" label="Résumé" :value="$release->summary" :rows="3" :maxlength="1500" hint="Facultatif : deux phrases pour présenter la version." />
            </div>

            <h3>Changements</h3>
            <div class="form-grid">
                @foreach(\App\Models\AppRelease::TYPES as $type => $label)
                    <x-admin.textarea :name="'changes_'.$type" :label="$label" :value="$lines[$type]" :rows="5" :maxlength="8000" :placeholder="$placeholders[$type]" />
                @endforeach
            </div>

            <h3>Publication</h3>
            <div class="form-grid">
                <div class="field {{ $errors->has('status') ? 'has-error' : '' }}">
                    <label for="f-status">Statut</label>
                    <select id="f-status" name="status">
                        @foreach(\App\Models\AppRelease::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $release->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field {{ $errors->has('published_at') ? 'has-error' : '' }}">
                    <label for="f-published_at">Date de publication</label>
                    <input id="f-published_at" type="datetime-local" name="published_at" value="{{ $publishedValue }}">
                    <p class="hint">Vide : la date d’enregistrement est utilisée à la publication.</p>
                    @error('published_at')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>
@endsection
