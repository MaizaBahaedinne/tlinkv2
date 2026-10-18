@extends('admin.layouts.app')

@php($editing = $department->exists)

@section('title', $editing ? 'Modifier le département' : 'Nouveau département')
@section('section', 'Départements')

@section('content')
    <x-admin.page-header :title="$editing ? 'Modifier « '.$department->name.' »' : 'Nouveau département'" subtitle="Un département est rattaché à une entité.">
        <x-slot:actions>
            <a href="{{ route('admin.departments.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à la liste</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $editing ? route('admin.departments.update', $department) : route('admin.departments.store') }}" class="card card-flush" style="max-width:860px">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card-body">
            <div class="form-grid">
                <x-admin.select name="entity_id" label="Entité" :options="$entities" :value="$department->entity_id" required />
                <x-admin.input name="name" label="Nom du département" :value="$department->name" required :maxlength="120" />
                <x-admin.textarea name="description" label="Description" :value="$department->description" :maxlength="1000" />
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.departments.index') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $editing ? 'Enregistrer' : 'Créer le département' }}</button>
        </div>
    </form>
@endsection
