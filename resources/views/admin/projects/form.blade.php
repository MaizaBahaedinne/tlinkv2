@extends('admin.layouts.app')

@php
    $pageTitle = $project->text('name', 'fr');
@endphp
@section('title', 'Page du projet')
@section('section', 'Projets')

@section('content')
    <x-admin.page-header :title="$pageTitle" subtitle="Page publique du projet. Un champ vide reprend le français, puis les informations du périmètre (nom, description). Le nom et le logo du périmètre se gèrent dans Structure > Périmètres.">
        <x-slot:actions>
            <a href="{{ route('admin.projects.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux projets</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ route('admin.projects.update', ['scope' => $project->scope]) }}" class="card card-flush" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card-body">
            <h3 style="margin-top:0">Présentation</h3>

            @foreach(['name' => ['Nom du projet', 'text', 160], 'summary' => ['Résumé (affiché sur la liste)', 'textarea', 400], 'body' => ['Présentation détaillée', 'textarea', 8000]] as $field => [$label, $type, $max])
                <div class="form-grid" style="margin-bottom:16px">
                    @foreach($locales as $code => $meta)
                        @php
                            $key = $field.'.'.$code;
                        @endphp
                        <div class="field span-2 {{ $errors->has($key) ? 'has-error' : '' }}">
                            <label for="p-{{ $field }}-{{ $code }}">{{ $label }} ({{ $meta['name'] }})</label>
                            @if($type === 'textarea')
                                <textarea id="p-{{ $field }}-{{ $code }}" name="{{ $field }}[{{ $code }}]" rows="{{ $field === 'body' ? 7 : 3 }}" maxlength="{{ $max }}" dir="{{ $meta['dir'] }}">{{ old($key, data_get($project->{$field}, $code)) }}</textarea>
                            @else
                                <input id="p-{{ $field }}-{{ $code }}" type="text" name="{{ $field }}[{{ $code }}]" maxlength="{{ $max }}" dir="{{ $meta['dir'] }}" value="{{ old($key, data_get($project->{$field}, $code)) }}" placeholder="{{ $project->text('name', 'fr') }}">
                            @endif
                            @error($key)<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            @endforeach

            <h3>Chiffres clés</h3>
            <p class="text-sm text-muted">Jusqu’à {{ \App\Models\SiteProject::MAX_FIGURES }} chiffres affichés en grand sur la page (ex. « 1 200 » — « Jeunes formés »). Les lignes vides sont ignorées ; le chiffre est libre (« 85 % », « +40 »).</p>
            @php
                $rows = old('stats', $project->stats ?? []);
                $rows = array_values((array) $rows);
                $rows = array_pad($rows, \App\Models\SiteProject::MAX_FIGURES, []);
            @endphp
            @foreach($rows as $i => $row)
                <div class="form-grid" style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--line)">
                    <div class="field {{ $errors->has("stats.$i.value") ? 'has-error' : '' }}">
                        <label for="s-{{ $i }}-value">Chiffre {{ $i + 1 }}</label>
                        <input id="s-{{ $i }}-value" type="text" name="stats[{{ $i }}][value]" maxlength="30" value="{{ $row['value'] ?? '' }}">
                        @error("stats.$i.value")<p class="error">{{ $message }}</p>@enderror
                    </div>
                    @foreach($locales as $code => $meta)
                        <div class="field {{ $errors->has("stats.$i.label.$code") ? 'has-error' : '' }}">
                            <label for="s-{{ $i }}-{{ $code }}">Libellé ({{ $meta['name'] }})</label>
                            <input id="s-{{ $i }}-{{ $code }}" type="text" name="stats[{{ $i }}][label][{{ $code }}]" maxlength="80" dir="{{ $meta['dir'] }}" value="{{ $row['label'][$code] ?? '' }}">
                            @error("stats.$i.label.$code")<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            @endforeach

            <h3>Paramètres</h3>
            <div class="form-grid">
                <div class="field {{ $errors->has('status') ? 'has-error' : '' }}">
                    <label for="p-status">Statut</label>
                    <select id="p-status" name="status">
                        @foreach(\App\Models\SiteProject::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-admin.input name="started_year" type="number" label="Année de lancement (optionnel)" :value="old('started_year', $project->started_year)" />
                <x-admin.input name="website_url" type="url" label="Site du projet (optionnel)" :value="old('website_url', $project->website_url)" placeholder="https://" :maxlength="255" />
                <x-admin.input name="sort_order" type="number" label="Ordre d’affichage" :value="old('sort_order', $project->sort_order)" />
                <div class="field span-2 {{ $errors->has('cover') ? 'has-error' : '' }}">
                    <label for="p-cover">Image de couverture (jpg, png, webp — 4 Mo max)</label>
                    <input id="p-cover" type="file" name="cover" accept="image/png,image/jpeg,image/webp">
                    @if($cover = $project->coverUrl())
                        <img src="{{ $cover }}" alt="Couverture actuelle" style="margin-top:6px;max-width:100%;height:120px;object-fit:cover;border-radius:10px">
                        <label class="check" style="margin-top:8px"><input type="checkbox" name="cover_remove" value="1"> Supprimer cette image</label>
                    @endif
                    @error('cover')<p class="error">{{ $message }}</p>@enderror
                </div>
                <label class="check span-2"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $project->is_published))> Afficher ce projet sur le site</label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>

    @if($project->exists)
        <form method="post" action="{{ route('admin.projects.destroy', ['scope' => $project->scope]) }}" style="margin-top:12px" onsubmit="return confirm('Effacer le contenu de cette page (textes, chiffres, couverture) ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Effacer le contenu de la page</button>
        </form>
    @endif
@endsection
