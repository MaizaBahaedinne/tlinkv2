@extends('admin.layouts.app')

@section('title', 'Modifier la charte')
@section('section', 'Marketing et partenariats')

@section('content')
    <x-admin.page-header :title="'Charte — '.$entityLabel" subtitle="Utilisez {entite} dans le texte : il est remplacé par le nom de l’entité. Chaque modification crée une nouvelle version ; les signatures déjà données gardent leur texte d’origine.">
        <x-slot:actions>
            <a href="{{ route('admin.marketing.charters.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ route('admin.marketing.charters.update') }}" class="card">
        @csrf
        @method('PUT')
        <input type="hidden" name="entity" value="{{ $entity }}">
        <div class="card-body">
            <div class="form-grid">
                <x-admin.input name="title" label="Titre" :value="$title" :required="true" maxlength="190" :span="true" />
                <x-admin.textarea name="body" label="Texte de la charte" :value="$body" rows="18" maxlength="20000" :required="true" :span="true" />
            </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button></div>
    </form>
@endsection
