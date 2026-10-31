@extends('admin.layouts.app')

@section('title', 'Catégories et couleurs')
@section('section', 'Tunivisions Stores')

@section('content')
    <x-admin.page-header title="Catégories, types, couleurs et tailles" subtitle="Les listes réutilisées par tous les articles. Supprimer un élément le retire des articles qui l’utilisent." />

    @include('admin.store._tabs')

    @php
        $blocks = [
            ['kind' => 'categories', 'title' => 'Catégories', 'sub' => 'Pour classer le catalogue.', 'items' => $categories, 'counted' => true],
            ['kind' => 'types', 'title' => 'Types d’article', 'sub' => 'T-shirt, sweat, accessoire…', 'items' => $types, 'counted' => true],
            ['kind' => 'colors', 'title' => 'Couleurs', 'sub' => 'Disponibles pour les variantes.', 'items' => $colors, 'counted' => false],
            ['kind' => 'sizes', 'title' => 'Tailles', 'sub' => 'S, M, L… ou « Unique ».', 'items' => $sizes, 'counted' => false],
        ];
    @endphp

    <div class="grid-2">
        @foreach($blocks as $block)
            @php
                $kind = $block['kind'];
            @endphp
            <article class="card card-flush">
                <div class="card-head">
                    <div>
                        <h2>{{ $block['title'] }}</h2>
                        <p class="sub">{{ $block['sub'] }}</p>
                    </div>
                    <span class="badge badge-neutral">{{ $block['items']->count() }}</span>
                </div>

                @if($block['items']->count())
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th class="w-sm">Ordre</th>
                                    @if($kind === 'categories')<th>Active</th>@endif
                                    @if($block['counted'])<th class="num">Articles</th>@endif
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($block['items'] as $item)
                                    <tr>
                                        <td>
                                            <form id="ru-{{ $kind }}-{{ $item->id }}" method="post" action="{{ route('admin.store.refs.update', [$kind, $item->id]) }}">@csrf @method('PUT')</form>
                                            <form id="rd-{{ $kind }}-{{ $item->id }}" method="post" action="{{ route('admin.store.refs.destroy', [$kind, $item->id]) }}" onsubmit="return confirm('Supprimer cet élément ?')">@csrf @method('DELETE')</form>
                                            <div class="inline-form" style="flex-wrap:nowrap">
                                                @if($kind === 'colors')<input form="ru-{{ $kind }}-{{ $item->id }}" type="color" name="hex" value="{{ $item->hex }}" aria-label="Couleur">@endif
                                                <input form="ru-{{ $kind }}-{{ $item->id }}" type="text" name="name" value="{{ $item->name }}" maxlength="80" required aria-label="Nom" style="min-width:110px">
                                            </div>
                                        </td>
                                        <td><input form="ru-{{ $kind }}-{{ $item->id }}" type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" aria-label="Ordre d’affichage"></td>
                                        @if($kind === 'categories')<td><label class="check"><input form="ru-{{ $kind }}-{{ $item->id }}" type="checkbox" name="is_active" value="1" @checked($item->is_active)></label></td>@endif
                                        @if($block['counted'])<td class="num">{{ $item->products_count }}</td>@endif
                                        <td class="cell-actions">
                                            <button form="ru-{{ $kind }}-{{ $item->id }}" type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                            <button form="rd-{{ $kind }}-{{ $item->id }}" type="submit" class="btn btn-ghost btn-sm btn-icon" aria-label="Supprimer"><x-admin.icon name="trash" :size="15" /></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-admin.empty icon="inbox" title="Aucun élément" text="Ajoutez le premier ci-dessous." />
                @endif

                <form method="post" action="{{ route('admin.store.refs.store', $kind) }}" class="card-body inline-form" style="background:#fefbfc">
                    @csrf
                    @if($kind === 'colors')<input type="color" name="hex" value="#CC0000" aria-label="Couleur">@endif
                    <input type="text" name="name" maxlength="80" required placeholder="Ajouter {{ $kind === 'sizes' || $kind === 'colors' ? 'une' : 'un' }} {{ $kind === 'categories' ? 'catégorie' : ($kind === 'types' ? 'type' : ($kind === 'colors' ? 'couleur' : 'taille')) }}…" aria-label="Nom" style="flex:1;min-width:150px;width:auto">
                    @if($kind === 'categories')<input type="hidden" name="is_active" value="1">@endif
                    <button type="submit" class="btn btn-primary btn-sm"><x-admin.icon name="plus" :size="15" /> Ajouter</button>
                </form>
            </article>
        @endforeach
    </div>
@endsection
