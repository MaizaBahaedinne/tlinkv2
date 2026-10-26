@extends('admin.layouts.app')

@section('title', 'Modules de l’application')
@section('section', 'Modules de l’application')

@section('content')
    <x-admin.page-header title="Modules de l’application" subtitle="Fonctionnalités de T.Link, organisées en modules et sous-modules. Chaque sous-module déclare les actions CRUD qu’il supporte : ce catalogue sert de base aux habilitations par poste.">
        <x-slot:actions>
            <form method="post" action="{{ route('admin.modules.sync') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-secondary"><x-admin.icon name="check" :size="16" /> Compléter le catalogue</button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->has('module'))
        <div class="alert alert-error" role="alert"><x-admin.icon name="x" /> <span>{{ $errors->first('module') }}</span></div>
    @endif

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head"><h2>Ajouter un module ou un sous-module</h2></div>
        <form method="post" action="{{ route('admin.modules.store') }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));padding:12px 16px 18px">
            @csrf
            <div>
                <label for="m-parent" class="text-sm">Module parent</label>
                <select id="m-parent" name="parent_id">
                    <option value="">— Module principal —</option>
                    @foreach($modules as $module)
                        <option value="{{ $module->id }}" @selected((string) old('parent_id') === (string) $module->id)>{{ $module->name }}</option>
                    @endforeach
                </select>
                @error('parent_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="m-key" class="text-sm">Clé technique</label>
                <input id="m-key" type="text" name="key" value="{{ old('key') }}" maxlength="80" placeholder="feed.polls" required>
                <p class="hint">Unique et non modifiable ensuite.</p>
                @error('key')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="m-name" class="text-sm">Nom</label>
                <input id="m-name" type="text" name="name" value="{{ old('name') }}" maxlength="120" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div style="grid-column:1/-1">
                <label for="m-desc" class="text-sm">Description</label>
                <input id="m-desc" type="text" name="description" value="{{ old('description') }}" maxlength="500">
            </div>
            <div style="grid-column:1/-1;display:flex;flex-wrap:wrap;gap:16px;align-items:center">
                <span class="text-sm">Actions supportées :</span>
                @foreach($actions as $code => $label)
                    <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="actions[]" value="{{ $code }}" @checked(in_array($code, old('actions', ['create', 'read', 'update', 'delete'])))> <span class="text-sm">{{ $label }}</span></label>
                @endforeach
                <label style="display:flex;align-items:center;gap:6px;margin-left:auto"><input type="checkbox" name="is_active" value="1" checked> <span class="text-sm">Actif</span></label>
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter</button>
            </div>
        </form>
    </article>

    <p class="text-sm text-muted">{{ $modules->count() }} module(s) principaux et {{ $total - $modules->count() }} sous-module(s).</p>

    @foreach($modules as $module)
        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head">
                <div>
                    <h2 style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">{{ $module->name }}
                        <code class="text-sm">{{ $module->key }}</code>
                        @unless($module->is_active)<x-admin.badge status="inactive" label="Désactivé" />@endunless
                    </h2>
                    @if($module->description)<p class="sub">{{ $module->description }}</p>@endif
                </div>
                <details style="margin-left:auto">
                    <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier le module</summary>
                    @include('admin.modules.partials.form', ['module' => $module, 'modules' => $modules, 'actions' => $actions])
                </details>
            </div>

            @if($module->children->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Sous-module</th><th>Actions CRUD</th><th>Statut</th><th></th></tr></thead>
                        <tbody>
                            @foreach($module->children as $child)
                                <tr>
                                    <td style="min-width:240px">
                                        <strong>{{ $child->name }}</strong> <code class="text-sm">{{ $child->key }}</code>
                                        @if($child->description)<div class="cell-sub">{{ $child->description }}</div>@endif
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:4px;flex-wrap:wrap">
                                            @foreach($actions as $code => $label)
                                                @php($on = in_array($code, $child->supportedActions()))
                                                <span class="badge {{ $on ? 'badge-info' : 'badge-neutral' }}" title="{{ $label }}{{ $on ? '' : ' (non supporté)' }}" style="{{ $on ? '' : 'opacity:.35' }}">{{ \App\Models\AppModule::ACTION_LETTERS[$code] }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td><x-admin.badge :status="$child->is_active ? 'active' : 'inactive'" :label="$child->is_active ? 'Actif' : 'Désactivé'" /></td>
                                    <td>
                                        <details>
                                            <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier</summary>
                                            @include('admin.modules.partials.form', ['module' => $child, 'modules' => $modules, 'actions' => $actions])
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body text-sm text-muted">Aucun sous-module.</div>
            @endif
        </article>
    @endforeach
@endsection
