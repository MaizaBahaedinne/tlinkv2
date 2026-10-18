@extends('layouts.base', ['title' => 'Gestion Structure | T.Link'])

@section('content')
<style>
.st-page { min-height: 100vh; background: #f3f6fb; padding: 20px; font-family: 'Manrope', ui-sans-serif, system-ui, sans-serif; color: #0f172a; }
.st-wrap { max-width: 1360px; margin: 0 auto; display: grid; gap: 14px; }
.st-card { background: #fff; border: 1px solid #dbe4f0; border-radius: 16px; padding: 16px; box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05); }
.st-top { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
.st-kicker { margin: 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em; color: #64748b; }
.st-title { margin: 6px 0 0; font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; font-size: 32px; line-height: 1; }
.st-sub { margin: 8px 0 0; color: #64748b; font-size: 14px; }
.st-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.st-btn { display: inline-block; text-decoration: none; border-radius: 10px; border: 1px solid #cfd9e8; background: #fff; color: #0f172a; font-size: 13px; font-weight: 600; padding: 8px 12px; }
.st-btn:hover { background: #f8fafc; }
.st-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.st-form { border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px; background: linear-gradient(180deg, #fff 0%, #f8fbff 100%); }
.st-form h3 { margin: 0; font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; font-size: 20px; }
.st-fields { margin-top: 10px; display: grid; gap: 8px; }
.st-fields label { font-size: 12px; color: #475569; font-weight: 700; }
.st-fields input,
.st-fields select,
.st-fields textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 13px; padding: 8px 10px; }
.st-fields textarea { min-height: 72px; resize: vertical; }
.st-check { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #334155; }
.st-check input { width: 16px; height: 16px; }
.st-submit { margin-top: 10px; border: 1px solid #0f2e5f; background: #0f2e5f; color: #fff; border-radius: 9px; padding: 8px 10px; font-size: 12px; font-weight: 700; cursor: pointer; }
.st-submit:hover { background: #0b2348; }
.st-entities { display: grid; gap: 12px; }
.st-entity { border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px; background: #fff; }
.st-head { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; flex-wrap: wrap; }
.st-name { margin: 0; font-family: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif; font-size: 22px; }
.st-meta { margin: 6px 0 0; font-size: 12px; color: #64748b; }
.st-badge { display: inline-block; border-radius: 999px; padding: 3px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
.st-badge.active { background: #dcfce7; color: #166534; }
.st-badge.inactive { background: #fee2e2; color: #991b1b; }
.st-cols { margin-top: 10px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.st-col { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; background: #f8fafc; }
.st-col h4 { margin: 0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
.st-list { margin-top: 8px; display: grid; gap: 6px; }
.st-item { border: 1px solid #dbe4f0; border-radius: 8px; padding: 7px 8px; background: #fff; font-size: 12px; color: #0f172a; }
.st-empty { margin-top: 8px; font-size: 12px; color: #64748b; }
@media (max-width: 1100px) { .st-grid { grid-template-columns: 1fr; } .st-cols { grid-template-columns: 1fr; } }
@media (max-width: 620px) { .st-page { padding: 10px; } .st-title { font-size: 26px; } }
</style>

<div class="st-page">
    <div class="st-wrap">
        @if($errors->any())
            <section class="st-card" style="border-color: #fecaca; background: #fff5f5;">
                <p class="st-kicker">Validation</p>
                @foreach($errors->all() as $error)
                    <p class="st-sub" style="margin-top: 4px; color: #991b1b;">- {{ $error }}</p>
                @endforeach
            </section>
        @endif

        <section class="st-card">
            <div class="st-top">
                <div>
                    <p class="st-kicker">Administration Structure</p>
                    <h1 class="st-title">Fondation, departements, institutions et clubs</h1>
                    <p class="st-sub">Page centralisee pour piloter la structure organisationnelle sans modifier la base manuellement.</p>
                </div>
                <div class="st-actions">
                    <a href="{{ route('admin.dashboard') }}" class="st-btn">Dashboard admin</a>
                    <a href="{{ route('home') }}" class="st-btn">Accueil</a>
                </div>
            </div>
        </section>

        <section class="st-grid">
            <article class="st-form">
                <h3>Nouvelle entite</h3>
                <form method="post" action="{{ route('admin.structure.entity.store') }}">
                    @csrf
                    <div class="st-fields">
                        <label>Nom</label>
                        <input type="text" name="name" required maxlength="120" placeholder="Fondation Tunivisions">

                        <label>Categorie</label>
                        <input type="text" name="category" required maxlength="50" placeholder="foundation">

                        <label>Description</label>
                        <textarea name="description" maxlength="500" placeholder="Description rapide"></textarea>

                        <label class="st-check"><input type="checkbox" name="is_active" value="1" checked>Entite active</label>
                    </div>
                    <button class="st-submit" type="submit">Ajouter l'entite</button>
                </form>
            </article>

            <article class="st-form">
                <h3>Nouveau departement</h3>
                <form method="post" action="{{ route('admin.structure.department.store') }}">
                    @csrf
                    <div class="st-fields">
                        <label>Entite parent</label>
                        <select name="entity_id" required>
                            <option value="">Selectionner</option>
                            @foreach($overview['entities'] as $entity)
                                <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                            @endforeach
                        </select>

                        <label>Nom</label>
                        <input type="text" name="name" required maxlength="120" placeholder="Departement formation">

                        <label>Description</label>
                        <textarea name="description" maxlength="500" placeholder="Description rapide"></textarea>
                    </div>
                    <button class="st-submit" type="submit">Ajouter le departement</button>
                </form>
            </article>

            <article class="st-form">
                <h3>Nouvelle institution</h3>
                <form method="post" action="{{ route('admin.structure.institution.store') }}">
                    @csrf
                    <div class="st-fields">
                        <label>Entite parent (optionnel)</label>
                        <select name="entity_id">
                            <option value="">Aucune</option>
                            @foreach($overview['entities'] as $entity)
                                <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                            @endforeach
                        </select>

                        <label>Nom</label>
                        <input type="text" name="name" required maxlength="120" placeholder="INSAT">

                        <label>Type</label>
                        <select name="institution_type" required>
                            @foreach($institutionTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>

                        <label>Description</label>
                        <textarea name="description" maxlength="500" placeholder="Description rapide"></textarea>

                        <label class="st-check"><input type="checkbox" name="is_active" value="1" checked>Institution active</label>
                    </div>
                    <button class="st-submit" type="submit">Ajouter l'institution</button>
                </form>
            </article>

            <article class="st-form">
                <h3>Nouveau club</h3>
                <form method="post" action="{{ route('admin.structure.club.store') }}">
                    @csrf
                    <div class="st-fields">
                        <label>Nom</label>
                        <input type="text" name="name" required maxlength="120" placeholder="Club IA">

                        <label>Entite (optionnel)</label>
                        <select name="entity_id">
                            <option value="">Aucune</option>
                            @foreach($overview['entities'] as $entity)
                                <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                            @endforeach
                        </select>

                        <label>Departement (optionnel)</label>
                        <select name="department_id">
                            <option value="">Aucun</option>
                            @foreach($overview['departments'] as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>

                        <label>Institution (optionnel)</label>
                        <select name="institution_id">
                            <option value="">Aucune</option>
                            @foreach($overview['institutions'] as $institution)
                                <option value="{{ $institution->id }}">{{ $institution->name }} ({{ strtoupper($institution->institution_type) }})</option>
                            @endforeach
                        </select>

                        <label>Gouvernorat (optionnel)</label>
                        <select name="governorate_id">
                            <option value="">Aucun</option>
                            @foreach($overview['governorates'] as $governorate)
                                <option value="{{ $governorate->id }}">{{ $governorate->name }}</option>
                            @endforeach
                        </select>

                        <label>Statut</label>
                        <select name="status" required>
                            @foreach($clubStatuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="st-submit" type="submit">Ajouter le club</button>
                </form>
            </article>
        </section>

        <section class="st-card st-entities">
            <div>
                <p class="st-kicker">Etat actuel</p>
                <h2 class="st-title" style="font-size: 26px;">Structure par entite</h2>
            </div>

            @foreach($overview['entities'] as $entity)
                <article class="st-entity">
                    <div class="st-head">
                        <div>
                            <h3 class="st-name">{{ $entity->name }}</h3>
                            <p class="st-meta">{{ $entity->category }} | {{ $entity->description ?: 'Sans description' }}</p>
                        </div>
                        <span class="st-badge {{ $entity->is_active ? 'active' : 'inactive' }}">{{ $entity->is_active ? 'active' : 'inactive' }}</span>
                    </div>

                    <div class="st-cols">
                        <section class="st-col">
                            <h4>Departements ({{ $entity->departments_count }})</h4>
                            @if($entity->departments->isEmpty())
                                <p class="st-empty">Aucun departement.</p>
                            @else
                                <div class="st-list">
                                    @foreach($entity->departments as $department)
                                        <div class="st-item">{{ $department->name }} ({{ $department->slug }})</div>
                                    @endforeach
                                </div>
                            @endif
                        </section>

                        <section class="st-col">
                            <h4>Institutions ({{ $entity->institutions_count }})</h4>
                            @if($entity->institutions->isEmpty())
                                <p class="st-empty">Aucune institution.</p>
                            @else
                                <div class="st-list">
                                    @foreach($entity->institutions as $institution)
                                        <div class="st-item">{{ $institution->name }} ({{ strtoupper($institution->institution_type) }})</div>
                                    @endforeach
                                </div>
                            @endif
                        </section>

                        <section class="st-col">
                            <h4>Clubs ({{ $entity->clubs_count }})</h4>
                            @if($entity->clubs->isEmpty())
                                <p class="st-empty">Aucun club.</p>
                            @else
                                <div class="st-list">
                                    @foreach($entity->clubs as $club)
                                        <div class="st-item">
                                            {{ $club->name }}
                                            @if($club->department)
                                                | Dept: {{ $club->department->name }}
                                            @endif
                                            @if($club->institution)
                                                | Inst: {{ $club->institution->name }}
                                            @endif
                                            | {{ strtoupper($club->status) }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    </div>
                </article>
            @endforeach
        </section>
    </div>
</div>
@endsection
