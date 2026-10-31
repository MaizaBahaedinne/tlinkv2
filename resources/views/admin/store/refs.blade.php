@extends('admin.layouts.app')

@section('title', 'Catégories, couleurs, tailles')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Catégories, types, couleurs et tailles" subtitle="Les listes réutilisées par tous les articles. Supprimer un élément le retire des articles qui l’utilisent." />

    @php
        $blocks = [
            ['kind' => 'categories', 'title' => 'Catégories', 'items' => $categories, 'counted' => true],
            ['kind' => 'types', 'title' => 'Types d’article', 'items' => $types, 'counted' => true],
            ['kind' => 'colors', 'title' => 'Couleurs', 'items' => $colors, 'counted' => false],
            ['kind' => 'sizes', 'title' => 'Tailles', 'items' => $sizes, 'counted' => false],
        ];
    @endphp

    <div class="grid-2">
        @foreach($blocks as $block)
            @php($kind = $block['kind'])
            <article class="card card-flush">
                <div class="card-head"><h2 class="card-title">{{ $block['title'] }}</h2></div>

                <div class="list">
                    @forelse($block['items'] as $item)
                        <div class="list-item" style="flex-wrap:wrap">
                            <form method="post" action="{{ route('admin.store.refs.update', [$kind, $item->id]) }}" class="inline-form grow">
                                @csrf
                                @method('PUT')
                                @if($kind === 'colors')<input type="color" name="hex" value="{{ $item->hex }}" aria-label="Couleur" style="width:44px;padding:2px">@endif
                                <input type="text" name="name" value="{{ $item->name }}" maxlength="80" required aria-label="Nom">
                                <input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" aria-label="Ordre" title="Ordre d’affichage" style="width:72px">
                                @if($kind === 'categories')<label style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Active</label>@endif
                                @if($block['counted'])<span class="text-sm text-muted">{{ $item->products_count }} article(s)</span>@endif
                                <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                            </form>
                            <form method="post" action="{{ route('admin.store.refs.destroy', [$kind, $item->id]) }}" onsubmit="return confirm('Supprimer « {{ addslashes($item->name) }} » ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" aria-label="Supprimer"><x-admin.icon name="trash" :size="15" /></button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-muted" style="padding:12px 16px">Aucun élément pour l’instant.</p>
                    @endforelse
                </div>

                <form method="post" action="{{ route('admin.store.refs.store', $kind) }}" class="inline-form" style="padding:12px 16px;border-top:1px solid var(--line, #eee)">
                    @csrf
                    @if($kind === 'colors')<input type="color" name="hex" value="#CC0000" aria-label="Couleur" style="width:44px;padding:2px">@endif
                    <input type="text" name="name" maxlength="80" required placeholder="Ajouter…" aria-label="Nom">
                    <input type="number" name="sort_order" min="0" placeholder="Ordre" aria-label="Ordre" style="width:72px">
                    @if($kind === 'categories')<input type="hidden" name="is_active" value="1">@endif
                    <button type="submit" class="btn btn-primary btn-sm"><x-admin.icon name="plus" :size="15" /> Ajouter</button>
                </form>
            </article>
        @endforeach
    </div>
@endsection
