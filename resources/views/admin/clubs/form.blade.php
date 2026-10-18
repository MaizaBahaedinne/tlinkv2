@extends('admin.layouts.app')

@php($editing = $club->exists)

@section('title', $editing ? 'Modifier le club' : 'Nouveau club')
@section('section', 'Clubs')

@section('content')
    <x-admin.page-header :title="$editing ? 'Modifier « '.$club->name.' »' : 'Nouveau club'" subtitle="Le département et l’institution proposés dépendent de l’entité choisie.">
        <x-slot:actions>
            <a href="{{ route('admin.clubs.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à la liste</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $editing ? route('admin.clubs.update', $club) : route('admin.clubs.store') }}" class="card card-flush" style="max-width:860px">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card-body">
            <div class="form-grid">
                <x-admin.input name="name" label="Nom du club" :value="$club->name" required :maxlength="120" />
                <x-admin.select name="status" label="Statut" :options="$statuses" :value="$club->status" required :placeholder="null" />
                <x-admin.select name="entity_id" label="Entité" :options="$entities" :value="$club->entity_id" :required="! $canBeGlobal" :placeholder="$canBeGlobal ? 'Aucune (globale)' : 'Sélectionner…'" />
                <x-admin.select name="governorate_id" label="Gouvernorat" :options="$governorates" :value="$club->governorate_id" placeholder="Aucun" />
                <x-admin.select name="department_id" label="Département" :options="$departments" :parents="$departmentParents" :value="$club->department_id" placeholder="Aucun" data-depends-on="entity_id" />
                <x-admin.select name="institution_id" label="Institution" :options="$institutions" :parents="$institutionParents" :value="$club->institution_id" placeholder="Aucune" data-depends-on="entity_id" />
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.clubs.index') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $editing ? 'Enregistrer' : 'Créer le club' }}</button>
        </div>
    </form>
@endsection
