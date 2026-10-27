@extends('admin.layouts.app')

@section('title', 'Projets')
@section('section', 'Projets')

@section('content')
    <x-admin.page-header title="Projets" subtitle="Les projets sont les périmètres de type « Projet ». Chacun a sa page sur le site vitrine : présentation et chiffres clés. Un projet sans contenu saisi s’affiche avec le nom, la description et le logo de son périmètre.">
        <x-slot:actions>
            <a href="{{ route('admin.scopes.edit') }}" class="btn btn-secondary"><x-admin.icon name="layers" :size="16" /> Gérer les périmètres</a>
            <a href="{{ route('site.projects', ['locale' => 'fr']) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-admin.icon name="external" :size="16" /> Voir le site</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Projets</h2>
                <p class="sub">{{ $projects->count() }} projet(s), dont {{ $projects->where('is_published', true)->count() }} visible(s) sur le site.</p>
            </div>
        </div>

        @if($projects->count())
            <div class="list">
                @foreach($projects as $project)
                    <div class="list-item" style="align-items:center;flex-wrap:wrap">
                        @if($cover = ($project->coverUrl() ?: $project->logoUrl()))
                            <img src="{{ $cover }}" alt="" loading="lazy" style="width:96px;height:64px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:10px;padding:4px">
                        @endif
                        <div class="grow" style="min-width:220px">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <strong style="display:inline">{{ $project->text('name', 'fr') }}</strong>
                                <x-admin.badge :status="$project->is_published ? 'active' : 'inactive'" :label="$project->is_published ? 'Visible' : 'Masqué'" />
                                <span class="text-sm text-muted">
                                    {{ \App\Models\SiteProject::STATUSES[$project->status] ?? $project->status }}
                                    · {{ count($project->figures('fr')) }} chiffre(s)
                                    · {{ $project->exists ? 'Page personnalisée' : 'Page par défaut' }}
                                </span>
                            </div>
                            <span class="text-sm text-muted">/projets/{{ $project->scope }}</span>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            @if($project->is_published)
                                <a href="{{ route('site.projects.show', ['locale' => 'fr', 'slug' => $project->scope]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-admin.icon name="external" :size="15" /> Voir</a>
                            @endif
                            <a href="{{ route('admin.projects.edit', ['scope' => $project->scope]) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier la page</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-admin.empty icon="layers" title="Aucun projet" text="Aucun périmètre n’est de type « Projet ». Ouvrez Structure > Périmètres et choisissez le type « Projet » pour chaque projet de la Fondation." />
        @endif
    </article>
@endsection
