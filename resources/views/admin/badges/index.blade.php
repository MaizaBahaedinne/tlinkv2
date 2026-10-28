@extends('admin.layouts.app')

@section('title', 'Badges et missions')
@section('section', 'Badges et missions')

@php
    $tierColors = ['bronze' => '#b45309', 'silver' => '#64748b', 'gold' => '#ca8a04', 'platinum' => '#7c3aed'];
@endphp

@section('content')
    <x-admin.page-header title="Badges et missions" subtitle="Un badge est décerné au membre qui réussit toutes ses missions ; chaque mission se mesure par des points de contrôle liés à l’activité sur la plateforme.">
        <x-slot:actions>
            <form method="post" action="{{ route('admin.badges.sync') }}" onsubmit="return confirm('Recalculer les missions de tous les membres et décerner les badges manquants ?')">
                @csrf
                <button type="submit" class="btn btn-secondary"><x-admin.icon name="activity" :size="16" /> Recalculer pour tous les membres</button>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head"><h2>Créer un badge</h2></div>
        <form method="post" action="{{ route('admin.badges.store') }}" class="grid" style="gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));padding:12px 16px 18px">
            @csrf
            <div>
                <label for="b-name" class="text-sm">Nom</label>
                <input id="b-name" type="text" name="name" value="{{ old('name') }}" maxlength="120" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="b-tier" class="text-sm">Catégorie</label>
                <select id="b-tier" name="tier" required>
                    @foreach(\App\Models\Badge::TIERS as $key => $tier)
                        <option value="{{ $key }}" @selected(old('tier', 'bronze') === $key)>{{ $tier['label'] }}</option>
                    @endforeach
                </select>
                @error('tier')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="b-icon" class="text-sm">Icône (classes Font Awesome)</label>
                <input id="b-icon" type="text" name="icon" value="{{ old('icon', 'fa-solid fa-medal') }}" maxlength="80" placeholder="fa-solid fa-medal">
                @error('icon')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div style="grid-column:1/-1">
                <label for="b-desc" class="text-sm">Description</label>
                <textarea id="b-desc" name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                @error('description')<p class="error">{{ $message }}</p>@enderror
            </div>
            <label style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="is_active" value="1" checked>
                <span class="text-sm">Actif (visible et décerné aux membres)</span>
            </label>
            <div style="grid-column:1/-1;display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Créer le badge</button>
            </div>
        </form>
    </article>

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Badges</h2>
                <p class="sub">{{ $badges->count() }} badge(s), dont {{ $badges->where('is_active', true)->count() }} actif(s).</p>
            </div>
        </div>

        @if($badges->count())
            <div class="list">
                @foreach($badges as $badge)
                    <div class="list-item" style="align-items:center;flex-wrap:wrap">
                        <span style="display:inline-flex;width:44px;height:44px;border-radius:999px;align-items:center;justify-content:center;color:#fff;background:{{ $tierColors[$badge->tier] ?? '#64748b' }}">
                            <i class="{{ $badge->iconClass() }}"></i>
                        </span>
                        <div class="grow" style="min-width:220px">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <strong style="display:inline">{{ $badge->name }}</strong>
                                <span class="text-sm" style="font-weight:600;color:{{ $tierColors[$badge->tier] ?? '#64748b' }}">{{ $badge->tierLabel() }}</span>
                                <x-admin.badge :status="$badge->is_active ? 'active' : 'inactive'" :label="$badge->is_active ? 'Actif' : 'Masqué'" />
                            </div>
                            <span class="text-sm text-muted">{{ $badge->missions_count }} mission(s) · décerné à {{ $badge->user_badges_count }} membre(s)</span>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            <a href="{{ route('admin.badges.edit', $badge) }}" class="btn btn-primary btn-sm"><x-admin.icon name="edit" :size="15" /> Missions</a>
                            <form method="post" action="{{ route('admin.badges.destroy', $badge) }}" onsubmit="return confirm('Supprimer ce badge, ses missions et les badges déjà décernés ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-admin.empty icon="award" title="Aucun badge" text="Créez un badge puis ajoutez-lui des missions et des points de contrôle." />
        @endif
    </article>
@endsection
