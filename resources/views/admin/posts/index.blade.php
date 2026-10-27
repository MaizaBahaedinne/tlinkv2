@extends('admin.layouts.app')

@section('title', 'Publications')
@section('section', 'Publications')

@section('content')
    <x-admin.page-header title="Publications" subtitle="Consultez et modérez les contenus publiés sur le fil T.Link. Les actualités du site vitrine se gèrent séparément, dans Contenu > Actualités du site." />

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.posts.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un contenu ou un auteur…">
                </div>
                <select name="type" aria-label="Type">
                    <option value="">Tous les types</option>
                    @foreach($types as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['type'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="visibility" aria-label="Visibilité">
                    <option value="">Toutes visibilités</option>
                    @foreach($visibilities as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['visibility'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="scope" aria-label="Périmètre">
                    <option value="">Toutes les entités</option>
                    @foreach($scopes as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['scope'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.posts.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($posts->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Contenu</th>
                            <th>Auteur</th>
                            <th class="hide-mobile">Portée</th>
                            <th class="hide-mobile">Interactions</th>
                            <th>Commentaires</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($posts as $post)
                            <tr>
                                <td style="max-width:360px">
                                    <div class="cell-main">{{ \Illuminate\Support\Str::limit(strip_tags((string) $post->content), 90) }}</div>
                                    <div class="cell-sub">{{ $types[$post->type] ?? $post->type }} · {{ $post->created_at?->format('d/m/Y H:i') }}</div>
                                </td>
                                <td>{{ $post->author?->name ?? '—' }}</td>
                                <td class="hide-mobile">
                                    <div>{{ \App\Support\OrgScopes::label($post->scope) ?? 'Global' }}</div>
                                    <div class="cell-sub">{{ $visibilities[$post->visibility] ?? $post->visibility }}</div>
                                </td>
                                <td class="hide-mobile text-sm">{{ $post->comments_count }} com. · {{ $post->reactions_count }} réact.</td>
                                <td>
                                    <form method="post" action="{{ route('admin.posts.comments', $post) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-secondary btn-sm">{{ $post->comments_enabled ? 'Activés' : 'Désactivés' }}</button>
                                    </form>
                                </td>
                                <td class="cell-actions">
                                    <x-admin.delete-button :action="route('admin.posts.destroy', $post)" message="La publication et ses commentaires, réactions et pièces jointes seront supprimés." title="Supprimer cette publication ?" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $posts->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="file-text" title="Aucune publication" text="Aucune publication ne correspond à ces critères." />
        @endif
    </article>
@endsection
