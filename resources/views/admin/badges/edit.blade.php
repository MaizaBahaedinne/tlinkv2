@extends('admin.layouts.app')

@section('title', $badge->name)
@section('section', 'Badges et missions')

@section('content')
    <x-admin.page-header :title="$badge->name" :subtitle="'Badge '.$badge->tierLabel().' : décerné quand toutes les missions actives ci-dessous sont réussies.'">
        <x-slot:actions>
            <a href="{{ route('admin.badges.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Tous les badges</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head"><h2>Badge</h2></div>
        <form method="post" action="{{ route('admin.badges.update', $badge) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));padding:12px 16px 18px">
            @csrf
            @method('PUT')
            <div>
                <label for="b-name" class="text-sm">Nom</label>
                <input id="b-name" type="text" name="name" value="{{ old('name', $badge->name) }}" maxlength="120" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="b-tier" class="text-sm">Catégorie</label>
                <select id="b-tier" name="tier" required>
                    @foreach(\App\Models\Badge::TIERS as $key => $tier)
                        <option value="{{ $key }}" @selected(old('tier', $badge->tier) === $key)>{{ $tier['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="b-icon" class="text-sm">Icône (classes Font Awesome)</label>
                <input id="b-icon" type="text" name="icon" value="{{ old('icon', $badge->icon) }}" maxlength="80">
                @error('icon')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="b-order" class="text-sm">Ordre d’affichage</label>
                <input id="b-order" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $badge->sort_order) }}">
            </div>
            <div style="grid-column:1/-1">
                <label for="b-desc" class="text-sm">Description</label>
                <textarea id="b-desc" name="description" rows="2" maxlength="1000">{{ old('description', $badge->description) }}</textarea>
            </div>
            <label style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="is_active" value="1" @checked($badge->is_active)>
                <span class="text-sm">Actif (visible et décerné aux membres)</span>
            </label>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
            </div>
        </form>
    </article>

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head"><h2>Ajouter une mission</h2></div>
        <form method="post" action="{{ route('admin.badges.missions.store', $badge) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));padding:12px 16px 18px">
            @csrf
            <div>
                <label for="m-title" class="text-sm">Titre de la mission</label>
                <input id="m-title" type="text" name="title" maxlength="160" required>
            </div>
            <div>
                <label for="m-desc" class="text-sm">Description (optionnelle)</label>
                <input id="m-desc" type="text" name="description" maxlength="1000">
            </div>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary btn-sm"><x-admin.icon name="plus" :size="15" /> Ajouter la mission</button>
            </div>
        </form>
    </article>

    @forelse($badge->missions as $mission)
        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head">
                <div>
                    <h2>{{ $loop->iteration }}. {{ $mission->title }}</h2>
                    <p class="sub">
                        <x-admin.badge :status="$mission->is_active ? 'active' : 'inactive'" :label="$mission->is_active ? 'Active' : 'Désactivée'" />
                        {{ $mission->checkpoints->count() }} point(s) de contrôle
                    </p>
                </div>
                <form method="post" action="{{ route('admin.badges.missions.destroy', $mission) }}" onsubmit="return confirm('Supprimer cette mission et ses points de contrôle ?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                </form>
            </div>

            <details style="padding:0 16px 12px">
                <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier la mission</summary>
                <form method="post" action="{{ route('admin.badges.missions.update', $mission) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="text-sm" for="mt-{{ $mission->id }}">Titre</label>
                        <input id="mt-{{ $mission->id }}" type="text" name="title" value="{{ $mission->title }}" maxlength="160" required>
                    </div>
                    <div>
                        <label class="text-sm" for="md-{{ $mission->id }}">Description</label>
                        <input id="md-{{ $mission->id }}" type="text" name="description" value="{{ $mission->description }}" maxlength="1000">
                    </div>
                    <div>
                        <label class="text-sm" for="mo-{{ $mission->id }}">Ordre</label>
                        <input id="mo-{{ $mission->id }}" type="number" name="sort_order" min="0" max="9999" value="{{ $mission->sort_order }}">
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;align-self:end">
                        <input type="checkbox" name="is_active" value="1" @checked($mission->is_active)>
                        <span class="text-sm">Active</span>
                    </label>
                    <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    </div>
                </form>
            </details>

            @if($mission->checkpoints->count())
                <div class="list">
                    @foreach($mission->checkpoints as $checkpoint)
                        <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                            <div class="grow" style="min-width:220px">
                                <strong style="display:inline">{{ $checkpoint->title }}</strong><br>
                                <span class="text-sm text-muted">{{ \App\Support\Gamification\ActivityCatalog::label($checkpoint->trigger_key) }} — objectif : {{ $checkpoint->target }}</span>
                            </div>
                            <form method="post" action="{{ route('admin.badges.checkpoints.destroy', $checkpoint) }}" onsubmit="return confirm('Supprimer ce point de contrôle ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                            </form>
                            <details style="flex-basis:100%">
                                <summary class="btn btn-secondary btn-sm" style="cursor:pointer">Modifier</summary>
                                <form method="post" action="{{ route('admin.badges.checkpoints.update', $checkpoint) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:10px">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label class="text-sm" for="ct-{{ $checkpoint->id }}">Titre</label>
                                        <input id="ct-{{ $checkpoint->id }}" type="text" name="title" value="{{ $checkpoint->title }}" maxlength="160" required>
                                    </div>
                                    <div>
                                        <label class="text-sm" for="ck-{{ $checkpoint->id }}">Activité mesurée</label>
                                        <select id="ck-{{ $checkpoint->id }}" name="trigger_key" required>
                                            @foreach($triggers as $group => $items)
                                                <optgroup label="{{ $group }}">
                                                    @foreach($items as $key => $label)
                                                        <option value="{{ $key }}" @selected($checkpoint->trigger_key === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-sm" for="cg-{{ $checkpoint->id }}">Objectif</label>
                                        <input id="cg-{{ $checkpoint->id }}" type="number" name="target" min="1" max="100000" value="{{ $checkpoint->target }}" required>
                                    </div>
                                    <div>
                                        <label class="text-sm" for="co-{{ $checkpoint->id }}">Ordre</label>
                                        <input id="co-{{ $checkpoint->id }}" type="number" name="sort_order" min="0" max="9999" value="{{ $checkpoint->sort_order }}">
                                    </div>
                                    <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                                    </div>
                                </form>
                            </details>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-muted" style="padding:0 16px 12px">Aucun point de contrôle : cette mission ne pourra pas être réussie.</p>
            @endif

            <form method="post" action="{{ route('admin.badges.checkpoints.store', $mission) }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));padding:12px 16px 18px;border-top:1px solid var(--line)">
                @csrf
                <div>
                    <label class="text-sm" for="nt-{{ $mission->id }}">Nouveau point de contrôle</label>
                    <input id="nt-{{ $mission->id }}" type="text" name="title" maxlength="160" placeholder="Ex. Publier 5 posts" required>
                </div>
                <div>
                    <label class="text-sm" for="nk-{{ $mission->id }}">Activité mesurée</label>
                    <select id="nk-{{ $mission->id }}" name="trigger_key" required>
                        @foreach($triggers as $group => $items)
                            <optgroup label="{{ $group }}">
                                @foreach($items as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm" for="ng-{{ $mission->id }}">Objectif</label>
                    <input id="ng-{{ $mission->id }}" type="number" name="target" min="1" max="100000" value="1" required>
                </div>
                <div style="align-self:end">
                    <button type="submit" class="btn btn-primary btn-sm"><x-admin.icon name="plus" :size="15" /> Ajouter</button>
                </div>
            </form>
        </article>
    @empty
        <article class="card card-flush">
            <x-admin.empty icon="award" title="Aucune mission" text="Ajoutez une mission : le badge sera décerné quand toutes ses missions seront réussies." />
        </article>
    @endforelse
@endsection
