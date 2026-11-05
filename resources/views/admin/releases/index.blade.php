@extends('admin.layouts.app')

@section('title', 'Versions et changements')
@section('section', 'Versions et changements')

@section('content')
    <x-admin.page-header title="Versions et changements" subtitle="Journal des mises à jour de la plateforme : nouveautés, améliorations, corrections et sécurité de chaque version. Réservé à l’administration globale.">
        <x-slot:actions>
            <a href="{{ route('admin.deployment.index') }}" class="btn btn-secondary"><x-admin.icon name="flag" :size="16" /> Déploiement</a>
            <a href="{{ route('admin.releases.create') }}" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Nouvelle version</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.releases.index') }}">
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    @foreach(\App\Models\AppRelease::STATUSES as $code => $label)
                        <option value="{{ $code }}" @selected($status === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($status)
                    <a href="{{ route('admin.releases.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($releases->count())
            <div class="list">
                @foreach($releases as $release)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="grow" style="min-width:260px">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <strong style="display:inline">{{ $release->version }}</strong>
                                <span>{{ $release->title }}</span>
                                @if($release->status === 'published')
                                    <x-admin.badge status="published" label="Publiée" />
                                @else
                                    <x-admin.badge status="draft" label="Brouillon" />
                                @endif
                            </div>
                            <span class="text-sm text-muted">
                                {{ $release->published_at?->format('d/m/Y H:i') ?? 'Non datée' }}
                                · {{ $release->changeCount() }} changement{{ $release->changeCount() > 1 ? 's' : '' }}
                                @if($release->author) · {{ $release->author->name }} @endif
                            </span>
                            @if($release->summary)
                                <p class="text-sm" style="margin:6px 0 0">{{ $release->summary }}</p>
                            @endif
                            @foreach($release->groupedChanges() as $type => $lines)
                                <p class="text-sm" style="margin:8px 0 2px"><strong>{{ \App\Models\AppRelease::TYPES[$type] }}</strong></p>
                                <ul class="text-sm text-muted" style="margin:0;padding-left:18px">
                                    @foreach($lines as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            @endforeach
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <form method="post" action="{{ route('admin.releases.toggle', $release) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-secondary btn-sm">{{ $release->status === 'published' ? 'Repasser en brouillon' : 'Publier' }}</button>
                            </form>
                            <a href="{{ route('admin.releases.edit', $release) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="edit" :size="15" /> Modifier</a>
                            <x-admin.delete-button :action="route('admin.releases.destroy', $release)" title="Supprimer cette version ?" message="La version {{ $release->version }} et ses notes seront supprimées." />
                        </div>
                    </div>
                @endforeach
            </div>
            {{ $releases->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="layers" title="Aucune version" text="Rédigez les notes de la première version avec le bouton « Nouvelle version »." />
        @endif
    </article>
@endsection
