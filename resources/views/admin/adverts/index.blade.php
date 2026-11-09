@extends('admin.layouts.app')

@section('title', 'Publicités')
@section('section', 'Publicités')

@section('content')
    <x-admin.page-header title="Publicités" subtitle="Bannières (image ou GIF) affichées dans le fil T.Link, sous la deuxième publication. Si plusieurs sont affichées, l’une d’elles est tirée au hasard à chaque chargement du fil.">
        <x-slot:actions>
            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-admin.icon name="external" :size="16" /> Voir le fil</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head"><h2>Ajouter une publicité</h2></div>
        <form method="post" action="{{ route('admin.adverts.store') }}" enctype="multipart/form-data" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));padding:12px 16px 18px">
            @csrf
            <div>
                <label for="a-title" class="text-sm">Titre (texte alternatif)</label>
                <input id="a-title" type="text" name="title" value="{{ old('title') }}" maxlength="160" required>
                @error('title')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="a-url" class="text-sm">Lien au clic (optionnel)</label>
                <input id="a-url" type="url" name="link_url" value="{{ old('link_url') }}" maxlength="255" placeholder="https://">
                @error('link_url')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="a-image" class="text-sm">Image ou GIF (jpg, png, webp, gif — 5 Mo max, format bannière conseillé)</label>
                <input id="a-image" type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif" required>
                @error('image')<p class="error">{{ $message }}</p>@enderror
            </div>
            <label style="display:flex;align-items:center;gap:8px;align-self:end">
                <input type="checkbox" name="is_active" value="1" checked>
                <span class="text-sm">Afficher dans le fil</span>
            </label>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
            </div>
        </form>
    </article>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Publicités</h2>
                <p class="sub">{{ $adverts->count() }} publicité(s), dont {{ $adverts->where('is_active', true)->count() }} affichée(s).</p>
            </div>
        </div>

        @if($adverts->count())
            <div class="list">
                @foreach($adverts as $advert)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <img src="{{ $advert->imageUrl() }}" alt="{{ $advert->title }}" loading="lazy" style="width:180px;height:72px;object-fit:cover;background:#fff;border:1px solid var(--line);border-radius:10px">
                        <div class="grow" style="min-width:220px">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <strong style="display:inline">{{ $advert->title }}</strong>
                                <x-admin.badge :status="$advert->is_active ? 'active' : 'inactive'" :label="$advert->is_active ? 'Affichée' : 'Masquée'" />
                                <span class="text-sm text-muted">Ordre {{ $advert->sort_order }}</span>
                            </div>
                            @if($advert->link_url)
                                <a class="text-sm" href="{{ $advert->link_url }}" target="_blank" rel="noopener noreferrer">{{ $advert->link_url }}</a>
                            @endif
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <form method="post" action="{{ route('admin.adverts.destroy', $advert) }}" onsubmit="return confirm('Supprimer cette publicité et son image ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                            </form>
                        </div>
                        <details style="flex-basis:100%">
                            <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier</summary>
                            <form method="post" action="{{ route('admin.adverts.update', $advert) }}" enctype="multipart/form-data" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="text-sm" for="t-{{ $advert->id }}">Titre</label>
                                    <input id="t-{{ $advert->id }}" type="text" name="title" value="{{ $advert->title }}" maxlength="160" required>
                                </div>
                                <div>
                                    <label class="text-sm" for="u-{{ $advert->id }}">Lien au clic</label>
                                    <input id="u-{{ $advert->id }}" type="url" name="link_url" value="{{ $advert->link_url }}" maxlength="255" placeholder="https://">
                                </div>
                                <div>
                                    <label class="text-sm" for="o-{{ $advert->id }}">Ordre d’affichage</label>
                                    <input id="o-{{ $advert->id }}" type="number" name="sort_order" min="0" max="9999" value="{{ $advert->sort_order }}">
                                </div>
                                <div>
                                    <label class="text-sm" for="i-{{ $advert->id }}">Nouvelle image ou GIF (optionnel)</label>
                                    <input id="i-{{ $advert->id }}" type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
                                </div>
                                <label style="display:flex;align-items:center;gap:8px;align-self:end">
                                    <input type="checkbox" name="is_active" value="1" @checked($advert->is_active)>
                                    <span class="text-sm">Afficher dans le fil</span>
                                </label>
                                <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
        @else
            <x-admin.empty icon="file-text" title="Aucune publicité" text="Ajoutez une image ou un GIF : il s’affichera dans le fil, sous la deuxième publication." />
        @endif
    </article>
@endsection
