@extends('admin.layouts.app')

@section('title', 'Périmètres')
@section('section', 'Périmètres')

@section('content')
    <x-admin.page-header title="Périmètres" subtitle="Ajoutez, modifiez ou supprimez des périmètres, et gérez leur héritage parent -> enfant." />

    @if($errors->any())
        <article class="card" style="margin-bottom: 12px; border-left: 4px solid #dc2626;">
            <div class="card-head">
                <h2>Erreur de validation</h2>
            </div>
            <div style="padding: 0 16px 12px;">
                <ul style="margin:0; padding-left: 18px; color:#b91c1c;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </article>
    @endif

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Catalogue et héritage des périmètres</h2>
                <p class="sub">Un parent couvre ses enfants. Le type (Management, Chapitre ou Projet) classe le périmètre : seuls les périmètres de type Projet ont une page sur le site public. Les périmètres système restent non supprimables.</p>
            </div>
        </div>

        <form method="post" action="{{ route('admin.scopes.update') }}" enctype="multipart/form-data" style="padding: 0 16px 16px;">
            @csrf
            @method('PUT')
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Slug</th>
                            <th>Logo</th>
                            <th>Libellé</th>
                            <th>Description</th>
                            <th>Périmètre</th>
                            <th>Type</th>
                            <th>Parent (héritage)</th>
                            <th>Suppression</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scopes as $slug => $label)
                            <tr>
                                <td><code>{{ $slug }}</code></td>
                                <td>
                                    @if(!empty($logos[$slug]))
                                        <img src="{{ $logos[$slug] }}" alt="Logo {{ $label }}" style="display:block; height:40px; max-width:120px; object-fit:contain; margin-bottom:6px;">
                                        <label style="display:flex; align-items:center; gap:6px;">
                                            <input type="checkbox" name="remove_logo[{{ $slug }}]" value="1">
                                            <span class="text-sm">Retirer</span>
                                        </label>
                                    @endif
                                    <input type="file" name="logo[{{ $slug }}]" accept="image/png,image/jpeg,image/webp">
                                    @error('logo.'.$slug)
                                        <p class="text-sm" style="color:#b91c1c; margin-top:4px;">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td>
                                    <input type="text" name="label[{{ $slug }}]" value="{{ old('label.'.$slug, $label) }}" maxlength="60" required>
                                    @error('label.'.$slug)
                                        <p class="text-sm" style="color:#b91c1c; margin-top:4px;">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td>
                                    <textarea name="description[{{ $slug }}]" rows="2" maxlength="300" placeholder="Description de l'espace">{{ old('description.'.$slug, $descriptions[$slug] ?? '') }}</textarea>
                                    @error('description.'.$slug)
                                        <p class="text-sm" style="color:#b91c1c; margin-top:4px;">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td>
                                    <div class="cell-main">{{ old('label.'.$slug, $label) }}</div>
                                    @if(in_array($slug, $coreScopes, true))
                                        <div class="cell-sub">Périmètre système</div>
                                    @else
                                        <div class="cell-sub">Périmètre personnalisable</div>
                                    @endif
                                </td>
                                <td>
                                    <select name="type[{{ $slug }}]" aria-label="Type de {{ $label }}">
                                        @foreach($typeLabels as $typeKey => $typeLabel)
                                            <option value="{{ $typeKey }}" @selected(old('type.'.$slug, $types[$slug] ?? 'project') === $typeKey)>{{ $typeLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('type.'.$slug)
                                        <p class="text-sm" style="color:#b91c1c; margin-top:4px;">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td>
                                    <select name="parent[{{ $slug }}]" aria-label="Parent {{ $label }}">
                                        <option value="">Aucun (racine)</option>
                                        @foreach($scopes as $pSlug => $pLabel)
                                            <option value="{{ $pSlug }}" @selected(($hierarchy[$slug] ?? null) === $pSlug)>{{ $pLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('parent.'.$slug)
                                        <p class="text-sm" style="color:#b91c1c; margin-top:4px;">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td>
                                    @if(in_array($slug, $coreScopes, true))
                                        <span class="text-muted text-sm">Protégé</span>
                                    @else
                                        <label style="display:flex; align-items:center; gap:8px;">
                                            <input type="checkbox" name="delete[{{ $slug }}]" value="1" @checked(old('delete.'.$slug))>
                                            <span class="text-sm">Supprimer</span>
                                        </label>
                                        @error('delete.'.$slug)
                                            <p class="text-sm" style="color:#b91c1c; margin-top:4px;">{{ $message }}</p>
                                        @enderror
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><input type="text" name="new_slug" value="{{ old('new_slug') }}" maxlength="50" placeholder="nouveau-scope"></td>
                            <td>
                                <input type="file" name="new_logo" accept="image/png,image/jpeg,image/webp">
                                @error('new_logo')
                                    <p class="text-sm" style="color:#b91c1c; margin-top:4px;">{{ $message }}</p>
                                @enderror
                            </td>
                            <td><input type="text" name="new_label" value="{{ old('new_label') }}" maxlength="60" placeholder="Nouveau périmètre"></td>
                            <td><textarea name="new_description" rows="2" maxlength="300" placeholder="Description du nouveau périmètre">{{ old('new_description') }}</textarea></td>
                            <td><span class="cell-sub">Nouveau périmètre</span></td>
                            <td>
                                <select name="new_type" aria-label="Type du nouveau périmètre">
                                    @foreach($typeLabels as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}" @selected(old('new_type', 'project') === $typeKey)>{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="new_parent" aria-label="Parent du nouveau périmètre">
                                    <option value="">Aucun (racine)</option>
                                    @foreach($scopes as $pSlug => $pLabel)
                                        <option value="{{ $pSlug }}" @selected(old('new_parent') === $pSlug)>{{ $pLabel }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><span class="text-muted text-sm">Ajout</span></td>
                        </tr>
                        @if($errors->has('new_slug') || $errors->has('new_label') || $errors->has('new_description') || $errors->has('new_parent') || $errors->has('parent'))
                            <tr>
                                <td colspan="8" style="color:#b91c1c;">
                                    @error('new_slug')<p style="margin: 4px 0;">{{ $message }}</p>@enderror
                                    @error('new_label')<p style="margin: 4px 0;">{{ $message }}</p>@enderror
                                    @error('new_description')<p style="margin: 4px 0;">{{ $message }}</p>@enderror
                                    @error('new_parent')<p style="margin: 4px 0;">{{ $message }}</p>@enderror
                                    @error('parent')<p style="margin: 4px 0;">{{ $message }}</p>@enderror
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:flex-end; margin-top: 12px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </article>
@endsection
