@extends('admin.layouts.app')

@section('title', 'Partenaires')
@section('section', 'Partenaires')

@section('content')
    <x-admin.page-header title="Partenaires" subtitle="Logos affichés sur la page d’accueil du site vitrine, dans l’ordre ci-dessous. Un partenaire désactivé reste enregistré mais n’est plus affiché.">
        <x-slot:actions>
            <a href="{{ route('site.root') }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-admin.icon name="external" :size="16" /> Voir le site</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head"><h2>Ajouter un partenaire</h2></div>
        <form method="post" action="{{ route('admin.partners.store') }}" enctype="multipart/form-data" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));padding:12px 16px 18px">
            @csrf
            <div>
                <label for="p-name" class="text-sm">Nom</label>
                <input id="p-name" type="text" name="name" value="{{ old('name') }}" maxlength="160" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="p-url" class="text-sm">Site web (optionnel)</label>
                <input id="p-url" type="url" name="website_url" value="{{ old('website_url') }}" maxlength="255" placeholder="https://">
                @error('website_url')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="p-logo" class="text-sm">Logo (jpg, png, webp — 3 Mo max)</label>
                <input id="p-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" required>
                @error('logo')<p class="error">{{ $message }}</p>@enderror
            </div>
            <label style="display:flex;align-items:center;gap:8px;align-self:end">
                <input type="checkbox" name="is_active" value="1" checked>
                <span class="text-sm">Afficher sur le site</span>
            </label>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
            </div>
        </form>
    </article>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Partenaires</h2>
                <p class="sub">{{ $partners->count() }} partenaire(s), dont {{ $partners->where('is_active', true)->count() }} affiché(s).</p>
            </div>
        </div>

        @if($partners->count())
            <div class="list">
                @foreach($partners as $partner)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <img src="{{ $partner->logoUrl() }}" alt="{{ $partner->name }}" loading="lazy" style="width:96px;height:64px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px">
                        <div class="grow" style="min-width:220px">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <strong style="display:inline">{{ $partner->name }}</strong>
                                <x-admin.badge :status="$partner->is_active ? 'active' : 'inactive'" :label="$partner->is_active ? 'Affiché' : 'Masqué'" />
                                <span class="text-sm text-muted">Ordre {{ $partner->sort_order }}</span>
                            </div>
                            @if($partner->website_url)
                                <a class="text-sm" href="{{ $partner->website_url }}" target="_blank" rel="noopener noreferrer">{{ $partner->website_url }}</a>
                            @endif
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <form method="post" action="{{ route('admin.partners.destroy', $partner) }}" onsubmit="return confirm('Supprimer ce partenaire et son logo ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                            </form>
                        </div>
                        <details style="flex-basis:100%">
                            <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier</summary>
                            <form method="post" action="{{ route('admin.partners.update', $partner) }}" enctype="multipart/form-data" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="text-sm" for="n-{{ $partner->id }}">Nom</label>
                                    <input id="n-{{ $partner->id }}" type="text" name="name" value="{{ $partner->name }}" maxlength="160" required>
                                </div>
                                <div>
                                    <label class="text-sm" for="u-{{ $partner->id }}">Site web</label>
                                    <input id="u-{{ $partner->id }}" type="url" name="website_url" value="{{ $partner->website_url }}" maxlength="255" placeholder="https://">
                                </div>
                                <div>
                                    <label class="text-sm" for="o-{{ $partner->id }}">Ordre d’affichage</label>
                                    <input id="o-{{ $partner->id }}" type="number" name="sort_order" min="0" max="9999" value="{{ $partner->sort_order }}">
                                </div>
                                <div>
                                    <label class="text-sm" for="l-{{ $partner->id }}">Nouveau logo (optionnel)</label>
                                    <input id="l-{{ $partner->id }}" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                                </div>
                                <label style="display:flex;align-items:center;gap:8px;align-self:end">
                                    <input type="checkbox" name="is_active" value="1" @checked($partner->is_active)>
                                    <span class="text-sm">Afficher sur le site</span>
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
            <x-admin.empty icon="building" title="Aucun partenaire" text="Ajoutez le logo d’un partenaire : il s’affichera sur la page d’accueil du site." />
        @endif
    </article>
@endsection
