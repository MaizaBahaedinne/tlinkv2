@extends('admin.layouts.app')

@php($editing = $institution->exists)

@section('title', $editing ? 'Modifier l’institution' : 'Nouvelle institution')
@section('section', 'Institutions')

@section('content')
    <x-admin.page-header :title="$editing ? 'Modifier « '.$institution->name.' »' : 'Nouvelle institution'" subtitle="Université, lycée ou autre établissement partenaire.">
        <x-slot:actions>
            <a href="{{ route('admin.institutions.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à la liste</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $editing ? route('admin.institutions.update', $institution) : route('admin.institutions.store') }}" class="card card-flush" style="max-width:860px">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card-body">
            <div class="form-grid">
                <x-admin.input name="name" label="Nom" :value="$institution->name" required :maxlength="120" />
                <x-admin.select name="institution_type" label="Type" :options="$types" :value="$institution->institution_type" required placeholder="Choisir un type…" />
                <x-admin.select name="entity_id" label="Entité de rattachement" :options="$entities" :value="$institution->entity_id" :required="! $canBeGlobal" :placeholder="$canBeGlobal ? 'Aucune (globale)' : 'Sélectionner…'" :span="true" />
                <x-admin.textarea name="description" label="Description" :value="$institution->description" :maxlength="1000" />
                <div class="field span-2">
                    <input type="hidden" name="is_active" value="0">
                    <label class="check">
                        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $institution->is_active))>
                        Institution active
                    </label>
                    @error('is_active')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.institutions.index') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $editing ? 'Enregistrer' : 'Créer l’institution' }}</button>
        </div>
    </form>
@endsection
