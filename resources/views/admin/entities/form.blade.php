@extends('admin.layouts.app')

@php($editing = $entity->exists)

@section('title', $editing ? 'Modifier l’entité' : 'Nouvelle entité')
@section('section', 'Entités')

@section('content')
    <x-admin.page-header :title="$editing ? 'Modifier « '.$entity->name.' »' : 'Nouvelle entité'" subtitle="Les entités regroupent les départements, institutions et clubs.">
        <x-slot:actions>
            <a href="{{ route('admin.entities.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à la liste</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $editing ? route('admin.entities.update', $entity) : route('admin.entities.store') }}" class="card card-flush" style="max-width:860px">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card-body">
            <div class="form-grid">
                <x-admin.input name="name" label="Nom" :value="$entity->name" required :maxlength="120" />
                <x-admin.input name="category" label="Catégorie" :value="$entity->category" required :maxlength="50" list="category-list" hint="Ex. : Fondation, Branche, Faculté…" />
                <datalist id="category-list">
                    @foreach($categories as $category)
                        <option value="{{ $category }}"></option>
                    @endforeach
                </datalist>
                <x-admin.textarea name="description" label="Description" :value="$entity->description" :span="true" :maxlength="1000" />
                <div class="field span-2">
                    <input type="hidden" name="is_active" value="0">
                    <label class="check">
                        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $entity->is_active))>
                        Entité active
                    </label>
                    @error('is_active')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.entities.index') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $editing ? 'Enregistrer' : 'Créer l’entité' }}</button>
        </div>
    </form>
@endsection
