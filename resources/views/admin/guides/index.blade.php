@extends('admin.layouts.app')

@section('title', 'Guides d’utilisation')
@section('section', 'Guides d’utilisation')

@section('content')
    <x-admin.page-header title="Guides d’utilisation" subtitle="Bibliothèque de guides animés pas à pas pour former les membres : captures ou GIF, repère animé, notices et astuces. Un guide publié apparaît dans le menu de la photo de chaque membre.">
        <x-slot:actions>
            <a href="{{ route('guides.index') }}" class="btn btn-secondary"><x-admin.icon name="external" :size="16" /> Voir la bibliothèque</a>
            <a href="{{ route('admin.guides.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouveau guide</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.guides.index') }}">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un guide…" aria-label="Rechercher">
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    @foreach(\App\Models\Guide::STATUSES as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['status'] ?? null) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="category" aria-label="Catégorie">
                    <option value="">Toutes les catégories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? null) === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.guides.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($guides->count())
            <div class="list">
                @foreach($guides as $guide)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="grow" style="min-width:260px">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <strong style="display:inline">{{ $guide->title }}</strong>
                                @if($guide->isPublished())
                                    <x-admin.badge status="published" label="Publié" />
                                @else
                                    <x-admin.badge status="draft" label="Brouillon" />
                                @endif
                                @if($guide->audience === 'holders')
                                    <x-admin.badge status="pending" label="Titulaires de poste" />
                                @endif
                            </div>
                            <span class="text-sm text-muted">
                                {{ $guide->category ?? 'Sans catégorie' }}
                                · {{ $guide->steps_count }} étape{{ $guide->steps_count > 1 ? 's' : '' }}
                                · {{ $guide->completed_count }} lecture{{ $guide->completed_count > 1 ? 's' : '' }} terminée{{ $guide->completed_count > 1 ? 's' : '' }}
                            </span>
                            @if($guide->summary)
                                <p class="text-sm" style="margin:6px 0 0">{{ $guide->summary }}</p>
                            @endif
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            <form method="post" action="{{ route('admin.guides.toggle', $guide) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">{{ $guide->isPublished() ? 'Repasser en brouillon' : 'Publier' }}</button>
                            </form>
                            <a href="{{ route('guides.show', $guide->slug) }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener">Aperçu</a>
                            <a href="{{ route('admin.guides.edit', $guide) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>
                            <x-admin.delete-button :action="route('admin.guides.destroy', $guide)" title="Supprimer ce guide ?" message="Le guide « {{ $guide->title }} », ses étapes, ses images et la progression des membres seront supprimés." />
                        </div>
                    </div>
                @endforeach
            </div>
            {{ $guides->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="layers" title="Aucun guide" text="Créez le premier guide avec le bouton « Nouveau guide »." />
        @endif
    </article>
@endsection
