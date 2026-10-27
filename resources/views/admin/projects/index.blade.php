@extends('admin.layouts.app')

@section('title', 'Projets')
@section('section', 'Projets')

@section('content')
    <x-admin.page-header title="Projets" subtitle="Chaque projet publié a sa propre page sur le site vitrine : présentation et chiffres clés. Un projet non publié reste invisible.">
        <x-slot:actions>
            <a href="{{ route('site.root') }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-admin.icon name="external" :size="16" /> Voir le site</a>
            <a href="{{ route('admin.projects.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouveau projet</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Projets</h2>
                <p class="sub">{{ $projects->count() }} projet(s), dont {{ $projects->where('is_published', true)->count() }} publié(s).</p>
            </div>
        </div>

        @if($projects->count())
            <div class="list">
                @foreach($projects as $project)
                    <div class="list-item" style="align-items:center;flex-wrap:wrap">
                        @if($cover = $project->coverUrl())
                            <img src="{{ $cover }}" alt="" loading="lazy" style="width:96px;height:64px;object-fit:cover;border:1px solid var(--line);border-radius:10px">
                        @endif
                        <div class="grow" style="min-width:220px">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <strong style="display:inline">{{ $project->text('name', 'fr') }}</strong>
                                <x-admin.badge :status="$project->is_published ? 'active' : 'inactive'" :label="$project->is_published ? 'Publié' : 'Brouillon'" />
                                <span class="text-sm text-muted">{{ \App\Models\SiteProject::STATUSES[$project->status] ?? $project->status }} · {{ count($project->figures('fr')) }} chiffre(s) · Ordre {{ $project->sort_order }}</span>
                            </div>
                            <span class="text-sm text-muted">/projets/{{ $project->slug }}</span>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            @if($project->is_published)
                                <a href="{{ route('site.projects.show', ['locale' => 'fr', 'slug' => $project->slug]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-admin.icon name="external" :size="15" /> Voir</a>
                            @endif
                            <a href="{{ route('admin.projects.edit', $project) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>
                            <form method="post" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Supprimer ce projet et sa page ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-admin.empty icon="layers" title="Aucun projet" text="Créez la page d’un projet : elle apparaîtra dans le menu « Projets » du site dès sa publication." />
        @endif
    </article>
@endsection
