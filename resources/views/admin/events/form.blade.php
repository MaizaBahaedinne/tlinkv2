@extends('admin.layouts.app')

@php($editing = $event->exists)
@php($dt = fn ($value) => $value?->format('Y-m-d\TH:i'))

@section('title', $editing ? 'Modifier l’événement' : 'Nouvel événement')
@section('section', 'Événements')

@section('content')
    <x-admin.page-header :title="$editing ? 'Modifier « '.$event->title.' »' : 'Nouvel événement'" subtitle="Renseignez les informations pratiques et les règles d’inscription.">
        <x-slot:actions>
            <a href="{{ $editing ? route('admin.events.show', $event) : route('admin.events.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $editing ? route('admin.events.update', $event) : route('admin.events.store') }}" class="card card-flush" enctype="multipart/form-data">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card-body">
            <div class="form-grid">
                <x-admin.input name="title" label="Titre" :value="$event->title" required :maxlength="255" :span="true" />
                <x-admin.textarea name="description" label="Description" :value="$event->description" :maxlength="5000" />
                <x-admin.select name="scope" label="Périmètre organisateur" :options="$scopes" :value="$event->scope" :required="! $canBeGlobal" :placeholder="$canBeGlobal ? 'Aucune (global)' : 'Sélectionner…'" />
                <x-admin.select name="status" label="Statut" :options="$statuses" :value="$event->status" required :placeholder="null" />
                <x-admin.select name="governorate_id" label="Gouvernorat" :options="$governorates" :value="$event->governorate_id" placeholder="Non renseigné" />
                <x-admin.input name="location" label="Lieu (adresse, salle…)" :value="$event->location" :maxlength="255" />
                <div class="field span-2 {{ $errors->has('cover') ? 'has-error' : '' }}">
                    <label for="f-cover">Photo de couverture</label>
                    <input id="f-cover" type="file" name="cover" accept="image/png,image/jpeg,image/webp">
                    <p class="hint">jpg, png ou webp, 4 Mo max. Affichée sur la page de l’événement et dans les listes du site.</p>
                    @if($coverUrl = $event->coverUrl())
                        <img src="{{ $coverUrl }}" alt="Couverture actuelle" style="margin-top:8px;max-width:100%;height:140px;object-fit:cover;border-radius:10px">
                        <label class="check" style="margin-top:8px"><input type="checkbox" name="cover_remove" value="1"> Supprimer cette photo</label>
                    @endif
                    @error('cover')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-admin.input name="starts_at" type="datetime-local" label="Début" :value="$dt($event->starts_at)" required />
                <x-admin.input name="ends_at" type="datetime-local" label="Fin" :value="$dt($event->ends_at)" />
                <x-admin.input name="registration_starts_at" type="datetime-local" label="Ouverture des inscriptions" :value="$dt($event->registration_starts_at)" />
                <x-admin.input name="registration_ends_at" type="datetime-local" label="Clôture des inscriptions" :value="$dt($event->registration_ends_at)" />
                <x-admin.input name="capacity" type="number" label="Capacité" :value="$event->capacity" hint="Laisser vide pour une capacité illimitée." min="1" />
                <x-admin.input name="price" type="number" label="Prix de participation (TND)" :value="$event->price !== null ? (float) $event->price : null" hint="0 = gratuit. Laisser vide pour ne rien afficher." min="0" step="0.01" />
                <div class="field">
                    <label>Validation</label>
                    <input type="hidden" name="approval_required" value="0">
                    <label class="check" style="margin-top:10px">
                        <input type="checkbox" name="approval_required" value="1" @checked((bool) old('approval_required', $event->approval_required))>
                        Inscriptions soumises à validation
                    </label>
                    @error('approval_required')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ $editing ? route('admin.events.show', $event) : route('admin.events.index') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $editing ? 'Enregistrer' : 'Créer l’événement' }}</button>
        </div>
    </form>
@endsection
