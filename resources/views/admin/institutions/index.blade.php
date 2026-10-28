@extends('admin.layouts.app')

@section('title', 'Établissements')
@section('section', 'Établissements')

@section('content')
    <x-admin.page-header title="Établissements" subtitle="Lycées, universités, facultés et écoles. Chaque club d’université ou de lycée est rattaché à son établissement ; le logo et les informations s’affichent sur les profils des membres.">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-modal-open="institution-create"><x-admin.icon name="plus" :size="16" /> Ajouter un établissement</button>
        </x-slot:actions>
    </x-admin.page-header>

    @error('institution')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

    <dialog id="institution-create" class="modal" aria-labelledby="institution-create-title" @if(old('_form') === 'new' && $errors->any()) data-modal-autoopen @endif>
        <div class="modal-head">
            <h2 id="institution-create-title">Ajouter un établissement</h2>
            <button type="button" class="btn btn-ghost btn-sm" data-modal-close aria-label="Fermer">✕</button>
        </div>
        <form method="post" action="{{ route('admin.institutions.store') }}" enctype="multipart/form-data" class="modal-form">
            @csrf
            <div class="modal-body">
                @include('admin.institutions._form', ['institution' => null, 'prefix' => 'new'])
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
            </div>
        </form>
    </dialog>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.institutions.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Rechercher un établissement…">
                </div>
                <select name="type" aria-label="Type">
                    <option value="">Tous les types</option>
                    @foreach(\App\Models\Institution::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="check"><input type="checkbox" name="sans_logo" value="1" @checked($onlyMissing)> Sans logo ({{ $withoutLogo }})</label>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($term !== '' || $type !== '' || $onlyMissing)
                    <a href="{{ route('admin.institutions.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($institutions->count())
            <div class="list">
                @foreach($institutions as $institution)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        @if($institution->logoUrl())
                            <img src="{{ $institution->logoUrl() }}" alt="{{ $institution->name }}" loading="lazy" style="width:72px;height:56px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px">
                        @else
                            <span style="display:inline-flex;width:72px;height:56px;align-items:center;justify-content:center;border:1px dashed var(--line);border-radius:10px;color:var(--muted)"><x-admin.icon name="graduation" :size="20" /></span>
                        @endif
                        <div class="grow" style="min-width:220px">
                            <strong style="display:inline">{{ $institution->name }}</strong>
                            <x-admin.badge :status="$institution->type === 'faculty' ? 'draft' : $institution->type" :label="$institution->typeLabel()" />
                            @unless($institution->is_active)<x-admin.badge status="inactive" />@endunless
                            <div class="text-sm text-muted">
                                {{ collect([$institution->parent?->name, $institution->governorate?->name, $institution->address])->filter()->implode(' · ') }}
                            </div>
                            @if($institution->safeUrl())
                                <a class="text-sm" href="{{ $institution->safeUrl() }}" target="_blank" rel="noopener noreferrer">{{ $institution->safeUrl() }}</a>
                            @endif
                            <div class="text-sm" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                                @forelse($institution->clubs as $club)
                                    <a class="btn btn-ghost btn-sm" href="{{ route('admin.clubs.show', $club) }}">{{ $club->title }}</a>
                                @empty
                                    <span class="text-muted">Aucun club rattaché</span>
                                @endforelse
                            </div>
                        </div>
                        <form method="post" action="{{ route('admin.institutions.destroy', $institution) }}" onsubmit="return confirm('Supprimer cet établissement et son logo ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                        </form>
                        <details style="flex-basis:100%" @if(old('_form') === 'i'.$institution->id && $errors->any()) open @endif>
                            <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier</summary>
                            <form method="post" action="{{ route('admin.institutions.update', $institution) }}" enctype="multipart/form-data" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                                @csrf
                                @method('PUT')
                                @include('admin.institutions._form', ['institution' => $institution, 'prefix' => 'i'.$institution->id])
                                <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
            {{ $institutions->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="graduation" title="Aucun établissement" text="Ajoutez un lycée, une université ou une faculté, puis rattachez-y ses clubs depuis la fiche du club." />
        @endif
    </article>
@endsection
