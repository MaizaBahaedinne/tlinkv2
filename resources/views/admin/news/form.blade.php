@extends('admin.layouts.app')

@php
    $pageTitle = $item->exists ? $item->text('title', 'fr') : 'Nouvelle actualité';
    $publishedValue = old('published_at', $item->published_at?->format('Y-m-d\TH:i'));
@endphp
@section('title', $item->exists ? 'Modifier une actualité' : 'Nouvelle actualité')
@section('section', 'Actualités du site')

@section('content')
    <x-admin.page-header :title="$pageTitle" subtitle="Un champ vide en anglais ou en arabe reprend le texte français. Paragraphes séparés par une ligne vide.">
        <x-slot:actions>
            <a href="{{ route('admin.news.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux actualités</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $item->exists ? route('admin.news.update', $item) : route('admin.news.store') }}" class="card card-flush" style="max-width:860px" enctype="multipart/form-data">
        @csrf
        @if($item->exists)
            @method('PUT')
        @endif

        <div class="card-body">
            <h3 style="margin-top:0">Contenu</h3>

            @foreach(['title' => ['Titre', 'text', 200], 'excerpt' => ['Résumé (affiché sur la liste)', 'textarea', 300], 'body' => ['Texte de l’article', 'textarea', 20000]] as $field => [$label, $type, $max])
                <div class="form-grid" style="margin-bottom:16px">
                    @foreach($locales as $code => $meta)
                        @php
                            $key = $field.'.'.$code;
                            $required = $field === 'title' && $code === 'fr';
                        @endphp
                        <div class="field span-2 {{ $errors->has($key) ? 'has-error' : '' }}">
                            <label for="n-{{ $field }}-{{ $code }}">{{ $label }} ({{ $meta['name'] }})@if($required) *@endif</label>
                            @if($type === 'textarea')
                                <textarea id="n-{{ $field }}-{{ $code }}" name="{{ $field }}[{{ $code }}]" rows="{{ $field === 'body' ? 10 : 3 }}" maxlength="{{ $max }}" dir="{{ $meta['dir'] }}">{{ old($key, data_get($item->{$field}, $code)) }}</textarea>
                            @else
                                <input id="n-{{ $field }}-{{ $code }}" type="text" name="{{ $field }}[{{ $code }}]" maxlength="{{ $max }}" dir="{{ $meta['dir'] }}" value="{{ old($key, data_get($item->{$field}, $code)) }}" @required($required)>
                            @endif
                            @error($key)<p class="error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            @endforeach

            <h3>Publication</h3>
            <div class="form-grid">
                <div class="field {{ $errors->has('status') ? 'has-error' : '' }}">
                    <label for="n-status">Statut</label>
                    <select id="n-status" name="status">
                        @foreach(\App\Models\SiteNews::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $item->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field {{ $errors->has('published_at') ? 'has-error' : '' }}">
                    <label for="n-published">Date de parution</label>
                    <input id="n-published" type="datetime-local" name="published_at" value="{{ $publishedValue }}">
                    <p class="text-sm text-muted">Vide : parution immédiate à l’enregistrement. Date future : parution programmée.</p>
                    @error('published_at')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field {{ $errors->has('scope') ? 'has-error' : '' }}">
                    <label for="n-scope">Périmètre (optionnel)</label>
                    <select id="n-scope" name="scope">
                        <option value="">Aucun</option>
                        @foreach($scopes as $slug => $name)
                            <option value="{{ $slug }}" @selected(old('scope', $item->scope) === $slug)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('scope')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field span-2 {{ $errors->has('cover') ? 'has-error' : '' }}">
                    <label for="n-cover">Image de couverture (jpg, png, webp — 4 Mo max)</label>
                    <input id="n-cover" type="file" name="cover" accept="image/png,image/jpeg,image/webp">
                    @if($cover = $item->coverUrl())
                        <img src="{{ $cover }}" alt="Couverture actuelle" style="margin-top:6px;max-width:100%;height:120px;object-fit:cover;border-radius:10px">
                        <label class="check" style="margin-top:8px"><input type="checkbox" name="remove_cover" value="1"> Supprimer cette image</label>
                    @endif
                    @error('cover')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>

    @if($item->exists)
        <form method="post" action="{{ route('admin.news.destroy', $item) }}" style="max-width:860px;margin-top:12px" onsubmit="return confirm('Supprimer définitivement cette actualité et son image ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer l’actualité</button>
        </form>
    @endif
@endsection
