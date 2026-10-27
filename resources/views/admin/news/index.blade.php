@extends('admin.layouts.app')

@section('title', 'Actualités du site')
@section('section', 'Actualités du site')

@section('content')
    <x-admin.page-header title="Actualités du site" subtitle="Articles affichés sur le site vitrine. Ils sont indépendants des publications du fil T.Link : rien n’est repris automatiquement d’un côté ou de l’autre.">
        <x-slot:actions>
            <a href="{{ route('site.news', ['locale' => 'fr']) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-admin.icon name="external" :size="16" /> Voir le site</a>
            <a href="{{ route('admin.news.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvelle actualité</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.news.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $search }}" placeholder="Rechercher un titre…">
                </div>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    @foreach(\App\Models\SiteNews::STATUSES as $code => $label)
                        <option value="{{ $code }}" @selected($status === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($search !== '' || $status)
                    <a href="{{ route('admin.news.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($news->count())
            <div class="list">
                @foreach($news as $item)
                    <div class="list-item" style="align-items:center;flex-wrap:wrap">
                        @if($cover = $item->coverUrl())
                            <img src="{{ $cover }}" alt="" loading="lazy" style="width:96px;height:64px;object-fit:cover;border:1px solid var(--line);border-radius:10px">
                        @endif
                        <div class="grow" style="min-width:220px">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <strong style="display:inline">{{ $item->text('title', 'fr') }}</strong>
                                @if($item->isVisible())
                                    <x-admin.badge status="active" label="En ligne" />
                                @elseif($item->status === 'published')
                                    <x-admin.badge status="pending" label="Programmée" />
                                @else
                                    <x-admin.badge status="inactive" label="Brouillon" />
                                @endif
                            </div>
                            <span class="text-sm text-muted">
                                {{ $item->published_at?->format('d/m/Y H:i') ?? 'Non datée' }}
                                @if($item->scope) · {{ $scopes[$item->scope] ?? $item->scope }} @endif
                                @if($item->author) · {{ $item->author->name }} @endif
                            </span>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            @if($item->isVisible())
                                <a href="{{ route('site.news.show', ['locale' => 'fr', 'news' => $item->id]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-admin.icon name="external" :size="15" /> Voir</a>
                            @endif
                            <a href="{{ route('admin.news.edit', $item) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>
                        </div>
                    </div>
                @endforeach
            </div>
            {{ $news->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="file-text" title="Aucune actualité" text="Créez la première actualité du site vitrine avec le bouton « Nouvelle actualité »." />
        @endif
    </article>
@endsection
