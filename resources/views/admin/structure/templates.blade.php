@extends('admin.layouts.app')

@section('title', 'Modèles de clubs')
@section('section', 'Organisation')

@section('content')
    <x-admin.page-header title="Modèles de clubs" subtitle="Un modèle d’équipe par type de club : départements, postes, titulaires autorisés et niveau d’accès. Tous les clubs du type le suivent.">
        <x-slot:actions>
            <a href="{{ route('admin.clubs.index') }}" class="btn btn-secondary"><x-admin.icon name="building" :size="16" /> Clubs</a>
        </x-slot:actions>
    </x-admin.page-header>

    <nav class="tabs" aria-label="Type de club" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
        @foreach($categories as $key => $label)
            <a href="{{ route('admin.templates.index', ['category' => $key]) }}" class="btn {{ $key === $category ? 'btn-primary' : 'btn-secondary' }}" @if($key === $category) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <x-admin.icon name="x" />
            <span>@foreach($errors->all() as $message){{ $message }} @endforeach</span>
        </div>
    @endif

    <div class="grid-3-1">
        <div style="display:grid;gap:16px;align-self:start">
            <form method="post" action="{{ route('admin.templates.update', $category) }}" class="card card-flush">
                @csrf
                @method('PUT')
                <div class="card-head">
                    <div>
                        <h2>Équipe type — {{ $categories[$category] }}</h2>
                        <p class="sub">{{ $clubCount }} club(s) suivent ce modèle. Les changements s’appliquent à tous dès l’enregistrement ; les postes occupés ne sont jamais retirés.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Intitulé</th>
                                <th>Rattaché à</th>
                                <th>Titulaires</th>
                                <th>Niveau d’accès</th>
                                <th class="hide-mobile">Clubs</th>
                                @if($canEdit)<th></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slots as $key => $slot)
                                @php $isPosition = $slot['kind'] === 'position'; @endphp
                                <tr>
                                    <td style="padding-inline-start:{{ 12 + $slot['depth'] * 22 }}px">
                                        <input type="text" name="slots[{{ $key }}][title]" value="{{ old('slots.'.$key.'.title', $slot['title']) }}" maxlength="120" required aria-label="Intitulé" @disabled(! $canEdit)>
                                        <div class="cell-sub">{{ $kinds[$slot['kind']] ?? $slot['kind'] }}</div>
                                    </td>
                                    <td>
                                        <select name="slots[{{ $key }}][parent_key]" aria-label="Rattaché à : {{ $slot['title'] }}" @disabled(! $canEdit)>
                                            <option value="">Le club</option>
                                            @foreach($parents[$key] as $parentKey => $parentTitle)
                                                <option value="{{ $parentKey }}" @selected(old('slots.'.$key.'.parent_key', $slot['parent']) === $parentKey)>{{ $parentTitle }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @if($isPosition)
                                            <select name="slots[{{ $key }}][capacity]" aria-label="Titulaires : {{ $slot['title'] }}" @disabled(! $canEdit)>
                                                <option value="single" @selected(old('slots.'.$key.'.capacity', $slot['capacity']) === 'single')>Un seul</option>
                                                <option value="multiple" @selected(old('slots.'.$key.'.capacity', $slot['capacity']) === 'multiple')>Plusieurs</option>
                                            </select>
                                        @else
                                            <input type="hidden" name="slots[{{ $key }}][capacity]" value="single">
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($isPosition)
                                            <select name="slots[{{ $key }}][access_level]" aria-label="Niveau d’accès : {{ $slot['title'] }}" @disabled(! $canEdit)>
                                                @foreach($levels as $level => $label)
                                                    <option value="{{ $level }}" @selected(old('slots.'.$key.'.access_level', $slot['access']) === $level)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="hidden" name="slots[{{ $key }}][access_level]" value="none">
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="hide-mobile">{{ $counts[$key] ?? 0 }}</td>
                                    @if($canEdit)
                                        <td class="cell-actions">
                                            <button type="submit" form="remove-{{ $key }}" class="btn btn-ghost btn-sm" onclick="return confirm('Retirer « {{ addslashes($slot['title']) }} » du modèle ?')">Retirer</button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($canEdit)
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer et appliquer aux clubs</button>
                    </div>
                @else
                    <div class="card-body"><p class="text-sm text-muted">Seuls les administrateurs globaux peuvent modifier les modèles.</p></div>
                @endif
            </form>

            @if($canEdit)
                @foreach($slots as $key => $slot)
                    <form id="remove-{{ $key }}" method="post" action="{{ route('admin.templates.destroy', [$category, $key]) }}" hidden>
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach

                <form method="post" action="{{ route('admin.templates.store', $category) }}" class="card card-flush">
                    @csrf
                    <div class="card-head"><div><h2>Ajouter un poste ou un département</h2><p class="sub">Il est créé dans tous les clubs {{ $categories[$category] }}.</p></div></div>
                    <div class="card-body">
                        <div class="form-grid">
                            <x-admin.input name="title" label="Intitulé" required :maxlength="120" />
                            <x-admin.select name="kind" label="Type" required :options="$kinds" />
                            <x-admin.select name="parent_key" label="Rattaché à" :options="collect($slots)->map(fn ($slot) => $slot['title'])->all()" placeholder="Le club" />
                            <x-admin.select name="capacity" label="Titulaires (poste)" :options="['single' => 'Un seul', 'multiple' => 'Plusieurs']" />
                            <x-admin.select name="access_level" label="Niveau d’accès (poste)" :options="$levels" />
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter au modèle</button>
                    </div>
                </form>
            @endif
        </div>

        <article class="card card-flush" style="align-self:start">
            <div class="card-head"><div><h2>Ce que permet chaque niveau</h2></div></div>
            <div class="card-body" style="display:grid;gap:14px">
                @foreach($levels as $level => $label)
                    <div>
                        <strong>{{ $label }}</strong>
                        <div class="chips" style="margin-top:6px">
                            @forelse($levelPermissions[$level] ?? [] as $permission)
                                <span class="chip">{{ $permission }}</span>
                            @empty
                                <span class="text-sm text-muted">Aucun droit d’administration.</span>
                            @endforelse
                        </div>
                    </div>
                @endforeach
                <p class="hint">Les droits s’exercent sur le périmètre du club. Les droits globaux et d’entité sont réservés aux postes de direction. Un poste ajouté à la main dans un club, hors modèle, n’est jamais modifié par ces réglages.</p>
            </div>
        </article>
    </div>
@endsection
