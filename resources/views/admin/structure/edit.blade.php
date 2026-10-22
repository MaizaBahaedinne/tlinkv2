@extends('admin.layouts.app')

@section('title', $node->title)
@section('section', 'Organisation')

@php
    $isPosition = $node->kind === 'position';
    $isClub = $node->kind === 'club';
    $disabled = ! $canEdit;
    $back = $isClub ? route('admin.clubs.index') : route('admin.postes.index');
@endphp

@section('content')
    <x-admin.page-header :title="$node->title" :subtitle="(\App\Models\OrgNode::KINDS[$node->kind] ?? $node->kind).($ancestors ? ' · '.collect($ancestors)->pluck('title')->take(-3)->implode(' › ') : '')">
        <x-slot:actions>
            @if($node->parent_id)
                <a href="{{ route('admin.postes.edit', $node->parent_id) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Bloc parent</a>
            @endif
            @if($isClub)
                <a href="{{ route('admin.clubs.show', $node) }}" class="btn btn-secondary"><x-admin.icon name="eye" :size="16" /> Page du club</a>
            @endif
            <a href="{{ $back }}" class="btn btn-secondary">Retour au tableau</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <x-admin.icon name="x" />
            <span>@foreach($errors->all() as $message){{ $message }} @endforeach</span>
        </div>
    @endif

    <div class="grid-3-1">
        <div style="display:grid;gap:22px;align-content:start">
            {{-- Fiche --}}
            <form method="post" action="{{ route('admin.postes.update', $node) }}" class="card card-flush">
                @csrf
                @method('PUT')
                <div class="card-head"><div><h2>Fiche</h2></div></div>
                <fieldset @disabled($disabled) style="border:0;margin:0;padding:0;min-width:0">
                    <div class="card-body">
                        <div class="form-grid">
                            <x-admin.input name="title" label="Intitulé" :value="$node->title" required :maxlength="255" />
                            <x-admin.input name="subtitle" label="Sous-titre" :value="$node->subtitle" :maxlength="255" />
                            <x-admin.textarea name="description" label="Description" :value="$node->description" :maxlength="5000" :rows="2" />

                            @if(! $isClub)
                                <x-admin.select name="kind" label="Type de bloc" :options="$kinds" :value="$node->kind" :required="true" :placeholder="null" />
                            @endif
                            @if($parentOptions !== [])
                                <x-admin.select name="parent_id" label="Rattaché à" :options="$parentOptions" :value="$node->parent_id" :required="true" :placeholder="null" />
                            @endif

                            <x-admin.select name="scope" label="Périmètre" :options="$scopes" :value="$node->scope" placeholder="Hérité du bloc parent"
                                :hint="$effectiveScope ? 'Périmètre effectif : '.\App\Support\OrgScopes::label($effectiveScope).($scopeInherited ? ' (hérité)' : '').'.' : 'Aucun périmètre : un poste sans périmètre ne donne aucun droit (sauf administrateur global).'" />

                            @if($isPosition)
                                <x-admin.select name="capacity" label="Titulaires" :options="$capacities" :value="$node->capacity" :required="true" :placeholder="null" />
                                <div class="span-2">
                                    @if($node->role_key)
                                        <label>Niveau d’accès sur T.Link</label>
                                        <p style="margin:4px 0 0"><span class="badge badge-{{ \App\Support\AccessLevels::BADGES[$node->access_level][1] ?? 'neutral' }}">{{ $levels[$node->access_level] ?? $node->access_level }}</span></p>
                                        <p class="hint" style="margin-top:6px">Poste de l’équipe standard des clubs : le niveau est le même dans tous les clubs et se règle dans <a href="{{ route('admin.roles.index') }}">Droits des clubs</a>.</p>
                                    @else
                                        <x-admin.select name="access_level" label="Niveau d’accès sur T.Link" :options="$levels" :value="$node->access_level" :required="true" :placeholder="null" />
                                    @endif
                                    <div class="chips" style="margin-top:8px" data-level-perms>
                                        @forelse($levelPermissions[old('access_level', $node->access_level)] ?? [] as $permission)
                                            <span class="chip">{{ $permission }}</span>
                                        @empty
                                            <span class="text-sm text-muted">Ce niveau ne donne aucun droit d’administration.</span>
                                        @endforelse
                                    </div>
                                    <p class="hint" style="margin-top:6px">Les droits s’appliquent au périmètre du poste, pour chaque personne qui l’occupe.</p>
                                </div>
                            @endif
                        </div>

                        @if($isClub)
                            <h3 class="text-sm text-muted" style="margin:24px 0 12px">Club</h3>
                            <div class="form-grid">
                                <x-admin.select name="status" label="Statut" :options="$clubStatuses" :value="$node->status" :placeholder="null" />
                                <x-admin.select name="governorate_id" label="Gouvernorat" :options="$governorates" :value="$node->governorate_id" placeholder="Non renseigné" />
                                <x-admin.input name="institution_name" label="Établissement" :value="$node->institution_name" :maxlength="255" />
                                <x-admin.select name="institution_type" label="Type d’établissement" :options="$institutionTypes" :value="$node->institution_type" placeholder="Non renseigné" />
                                <x-admin.input name="email" type="email" label="E-mail" :value="$node->email" :maxlength="255" />
                                <x-admin.input name="facebook_url" type="url" label="Page Facebook" :value="$node->facebook_url" :maxlength="500" />
                                <x-admin.input name="founded_on" type="date" label="Date de fondation" :value="$node->founded_on?->format('Y-m-d')" />
                                <div class="field">
                                    <label>&nbsp;</label>
                                    <input type="hidden" name="charter_signed" value="0">
                                    <label class="check"><input type="checkbox" name="charter_signed" value="1" @checked((bool) old('charter_signed', $node->charter_signed))> Charte signée</label>
                                </div>
                            </div>
                        @endif
                    </div>
                </fieldset>
                @if($canEdit)
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
                    </div>
                @endif
            </form>

            {{-- Sous-blocs --}}
            <article class="card card-flush">
                <div class="card-head"><div><h2>Sous-blocs</h2><p class="sub">Départements et postes placés directement sous « {{ $node->title }} ».</p></div></div>
                @if($children->count())
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>Bloc</th><th>Type</th><th>Accès</th><th>Titulaires</th><th></th></tr></thead>
                            <tbody>
                                @foreach($children as $child)
                                    @php [$short, $tone] = \App\Support\AccessLevels::BADGES[$child->access_level] ?? \App\Support\AccessLevels::BADGES['none']; @endphp
                                    <tr>
                                        <td><a href="{{ route('admin.postes.edit', $child) }}" class="cell-main">{{ $child->title }}</a></td>
                                        <td><span class="chip">{{ \App\Models\OrgNode::KINDS[$child->kind] ?? $child->kind }}</span></td>
                                        <td>@if($child->kind === 'position')<span class="badge badge-{{ $tone }}">{{ $short }}</span>@else<span class="text-muted">—</span>@endif</td>
                                        <td>
                                            @if($child->kind === 'position')
                                                @forelse($child->assignments as $assignment)
                                                    <span class="chip">{{ $assignment->person?->displayName() }}</span>
                                                @empty
                                                    <span class="badge badge-warning">Vacant</span>
                                                @endforelse
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="cell-actions"><a href="{{ route('admin.postes.edit', $child) }}" class="btn btn-secondary btn-sm">{{ $canEdit ? 'Modifier' : 'Voir' }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-admin.empty icon="layers" title="Aucun sous-bloc" />
                @endif

                @if($canEdit)
                    <form method="post" action="{{ route('admin.postes.store') }}" class="card-body" style="border-top:1px solid var(--line)">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $node->id }}">
                        <h3 class="text-sm text-muted" style="margin-bottom:12px">Ajouter un sous-bloc</h3>
                        <div class="form-grid">
                            <div class="field span-2">
                                <label for="add-title">Intitulé <span class="req">*</span></label>
                                <input id="add-title" name="title" type="text" required maxlength="255">
                            </div>
                            <div class="field">
                                <label for="add-kind">Type</label>
                                <select id="add-kind" name="kind">
                                    @foreach($kinds as $id => $name)
                                        <option value="{{ $id }}" @selected($id === 'position')>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="add-capacity">Titulaires</label>
                                <select id="add-capacity" name="capacity">
                                    @foreach($capacities as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field span-2">
                                <label for="add-access">Niveau d’accès</label>
                                <select id="add-access" name="access_level">
                                    @foreach($levels as $id => $name)
                                        <option value="{{ $id }}" @selected($id === 'none')>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div style="margin-top:14px"><button type="submit" class="btn btn-secondary"><x-admin.icon name="plus" :size="16" /> Ajouter</button></div>
                    </form>
                @endif
            </article>
        </div>

        <div style="display:grid;gap:22px;align-content:start">
            {{-- Titulaires --}}
            @if($isPosition)
                <article class="card card-flush">
                    <div class="card-head"><div><h2>Titulaires</h2><p class="sub">{{ $node->capacity === 'multiple' ? 'Plusieurs personnes possibles.' : 'Un seul titulaire.' }}</p></div></div>
                    <div class="card-body">
                        @forelse($holders as $assignment)
                            @php $person = $assignment->person; @endphp
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px">
                                <div class="cell-user">
                                    <x-admin.avatar :name="$person?->displayName() ?? '?'" :size="34" />
                                    <div>
                                        @if($person?->user_id && $canEdit)
                                            <a href="{{ route('admin.users.show', $person->user_id) }}" class="cell-main">{{ $person->displayName() }}</a>
                                        @else
                                            <span class="cell-main">{{ $person?->displayName() }}</span>
                                        @endif
                                        @if($canEdit && $person?->displayEmail())<div class="cell-sub">{{ $person->displayEmail() }}</div>@endif
                                    </div>
                                </div>
                                @if($canEdit && $person)
                                    <form method="post" action="{{ route('admin.postes.unassign', [$node, $person]) }}" data-confirm="{{ $person->displayName() }} ne sera plus titulaire de ce poste." data-confirm-title="Retirer ce titulaire ?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Retirer</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="badge badge-warning">Vacant</span>
                        @endforelse

                        @if($canEdit)
                            <form method="get" action="{{ route('admin.postes.edit', $node) }}" style="margin-top:18px">
                                <div class="field">
                                    <label for="f-u">Affecter un utilisateur</label>
                                    <div style="display:flex;gap:8px">
                                        <input id="f-u" type="search" name="u" value="{{ $term }}" placeholder="Nom ou e-mail…" minlength="2">
                                        <button type="submit" class="btn btn-secondary">Chercher</button>
                                    </div>
                                </div>
                            </form>

                            @if($term !== '')
                                @forelse($found as $user)
                                    <form method="post" action="{{ route('admin.postes.assign', $node) }}" style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                                        <div><div class="cell-main">{{ $user->name }}</div><div class="cell-sub">{{ str_ends_with((string) $user->email, '@legacy.invalid') ? '' : $user->email }}</div></div>
                                        <button type="submit" class="btn btn-primary btn-sm">Affecter</button>
                                    </form>
                                @empty
                                    <p class="text-sm text-muted" style="margin-top:10px">Aucun compte actif ne correspond.</p>
                                @endforelse
                            @endif
                        @endif
                    </div>
                </article>
            @endif

            {{-- Actions --}}
            @if($canEdit)
                <article class="card card-flush">
                    <div class="card-head"><div><h2>Actions</h2></div></div>
                    <div class="card-body" style="display:grid;gap:12px">
                        @if($isClub && ! $hasChildren)
                            <form method="post" action="{{ route('admin.postes.template', $node) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary" style="width:100%">Créer l’équipe standard</button>
                            </form>
                        @endif
                        <a href="{{ route('orgchart.index') }}" class="btn btn-ghost" style="width:100%">Voir dans l’organigramme</a>
                        @if($node->parent_id)
                            <form method="post" action="{{ route('admin.postes.destroy', $node) }}" data-confirm="{{ $hasChildren ? 'Ce bloc et tous ses sous-blocs seront supprimés.' : 'Cette action est définitive.' }}" data-confirm-title="Supprimer « {{ $node->title }} » ?">
                                @csrf
                                @method('DELETE')
                                @if($hasChildren)<input type="hidden" name="cascade" value="1">@endif
                                <button type="submit" class="btn btn-danger" style="width:100%">Supprimer ce bloc</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var select = document.getElementById('f-access_level');
            var box = document.querySelector('[data-level-perms]');
            if (!select || !box) return;
            var perms = @json($levelPermissions);
            select.addEventListener('change', function () {
                var list = perms[select.value] || [];
                box.textContent = '';
                if (!list.length) {
                    var none = document.createElement('span');
                    none.className = 'text-sm text-muted';
                    none.textContent = 'Ce niveau ne donne aucun droit d’administration.';
                    box.appendChild(none);
                    return;
                }
                list.forEach(function (label) {
                    var chip = document.createElement('span');
                    chip.className = 'chip';
                    chip.textContent = label;
                    box.appendChild(chip);
                });
            });
        })();
    </script>
@endpush
