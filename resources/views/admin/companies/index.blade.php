@extends('admin.layouts.app')

@section('title', 'Entreprises')
@section('section', 'Entreprises')

@section('content')
    <x-admin.page-header title="Entreprises" subtitle="Base des entreprises citées dans les expériences professionnelles des membres. Une entreprise est ajoutée automatiquement quand un membre la saisit ; ajoutez ici son logo pour décorer les profils.">
    </x-admin.page-header>

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head"><h2>Ajouter une entreprise</h2></div>
        <form method="post" action="{{ route('admin.companies.store') }}" enctype="multipart/form-data" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));padding:12px 16px 18px">
            @csrf
            <div>
                <label for="c-name" class="text-sm">Nom</label>
                <input id="c-name" type="text" name="name" value="{{ old('name') }}" maxlength="160" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="c-url" class="text-sm">Site web (optionnel)</label>
                <input id="c-url" type="url" name="website_url" value="{{ old('website_url') }}" maxlength="255" placeholder="https://">
                @error('website_url')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="c-logo" class="text-sm">Logo (jpg, png, webp — 3 Mo max)</label>
                <input id="c-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                @error('logo')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
            </div>
        </form>
    </article>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.companies.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Rechercher une entreprise…">
                </div>
                <label class="check"><input type="checkbox" name="sans_logo" value="1" @checked($onlyMissing)> Sans logo ({{ $withoutLogo }})</label>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($term !== '' || $onlyMissing)
                    <a href="{{ route('admin.companies.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($companies->count())
            <div class="list">
                @foreach($companies as $company)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        @if($company->logoUrl())
                            <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}" loading="lazy" style="width:72px;height:56px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px">
                        @else
                            <span style="display:inline-flex;width:72px;height:56px;align-items:center;justify-content:center;border:1px dashed var(--line);border-radius:10px;color:var(--muted)"><x-admin.icon name="building" :size="20" /></span>
                        @endif
                        <div class="grow" style="min-width:220px">
                            <strong style="display:inline">{{ $company->name }}</strong>
                            <div class="text-sm text-muted">
                                @if($company->creator)Ajoutée par {{ $company->creator->name }} · @endif{{ $company->created_at?->format('d/m/Y') }}
                            </div>
                            @if($company->safeUrl())
                                <a class="text-sm" href="{{ $company->safeUrl() }}" target="_blank" rel="noopener noreferrer">{{ $company->safeUrl() }}</a>
                            @endif
                        </div>
                        <form method="post" action="{{ route('admin.companies.destroy', $company) }}" onsubmit="return confirm('Supprimer cette entreprise et son logo ? Les profils gardent le nom saisi.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                        </form>
                        <details style="flex-basis:100%">
                            <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier</summary>
                            <form method="post" action="{{ route('admin.companies.update', $company) }}" enctype="multipart/form-data" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="text-sm" for="cn-{{ $company->id }}">Nom</label>
                                    <input id="cn-{{ $company->id }}" type="text" name="name" value="{{ $company->name }}" maxlength="160" required>
                                </div>
                                <div>
                                    <label class="text-sm" for="cu-{{ $company->id }}">Site web</label>
                                    <input id="cu-{{ $company->id }}" type="url" name="website_url" value="{{ $company->website_url }}" maxlength="255" placeholder="https://">
                                </div>
                                <div>
                                    <label class="text-sm" for="cl-{{ $company->id }}">{{ $company->logo_path ? 'Nouveau logo (optionnel)' : 'Logo' }}</label>
                                    <input id="cl-{{ $company->id }}" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                                </div>
                                @if($company->logo_path)
                                    <label style="display:flex;align-items:center;gap:8px;align-self:end">
                                        <input type="checkbox" name="remove_logo" value="1">
                                        <span class="text-sm">Retirer le logo</span>
                                    </label>
                                @endif
                                <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
            {{ $companies->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="building" title="Aucune entreprise" text="Les entreprises apparaissent ici dès qu’un membre en saisit une dans son profil." />
        @endif
    </article>
@endsection
