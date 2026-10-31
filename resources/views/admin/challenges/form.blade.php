@extends('admin.layouts.app')

@php($editing = $challenge->exists)
@php($dt = fn ($value) => $value?->format('Y-m-d\TH:i'))

@section('title', $editing ? 'Modifier le défi' : 'Nouveau défi')
@section('section', 'Défis')

@section('content')
    <x-admin.page-header :title="$editing ? 'Modifier « '.$challenge->title.' »' : 'Nouveau défi'" subtitle="Sujet, calendrier, barèmes de notation et comité d’évaluation.">
        <x-slot:actions>
            <a href="{{ $editing ? route('admin.challenges.show', $challenge) : route('admin.challenges.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $editing ? route('admin.challenges.update', $challenge) : route('admin.challenges.store') }}" class="card card-flush">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card-body">
            <h3>Sujet</h3>
            <div class="form-grid">
                <x-admin.input name="title" label="Nom du défi" :value="$challenge->title" required :maxlength="255" :span="true" />
                <x-admin.input name="theme" label="Thématique (sujet du défi)" :value="$challenge->theme" required :maxlength="255" :span="true" hint="Ex. : « Sensibilisation à l’environnement »." />
                <x-admin.textarea name="description" label="Description et consignes" :value="$challenge->description" :maxlength="5000" />
                <x-admin.select name="scope" label="Entité organisatrice (chapitre ou projet)" :options="$scopes" :value="$challenge->scope" required />
                <x-admin.select name="status" label="Statut" :options="\App\Models\Challenge::STATUSES" :value="$challenge->status" required :placeholder="null" hint="Publié : le défi devient visible des membres et les clubs peuvent y proposer des activités." />
                <div class="field span-2">
                    <span class="text-sm" style="font-weight:600">Clubs invités (périmètres)</span>
                    @php($invited = old('invited_scopes', $challenge->invited_scopes ?? []))
                    <div style="display:flex;flex-wrap:wrap;gap:6px 18px;margin-top:6px">
                        @foreach($allScopes as $slug => $name)
                            <label class="check"><input type="checkbox" name="invited_scopes[]" value="{{ $slug }}" @checked(in_array($slug, (array) $invited, true))> {{ $name }}</label>
                        @endforeach
                    </div>
                    <p class="hint">Les clubs de ces périmètres (et de leurs sous-périmètres) sont invités. Aucun choix : les clubs de l’entité organisatrice.</p>
                    @error('invited_scopes.*')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>

            <h3>Calendrier</h3>
            <div class="form-grid">
                <x-admin.input name="starts_at" type="datetime-local" label="Début du défi" :value="$dt($challenge->starts_at)" required />
                <x-admin.input name="ends_at" type="datetime-local" label="Fin du défi" :value="$dt($challenge->ends_at)" required hint="Dernier moment pour organiser une activité." />
                <x-admin.input name="evaluation_at" type="datetime-local" label="Date d’évaluation" :value="$dt($challenge->evaluation_at)" required hint="Réunion du comité." />
                <x-admin.input name="results_at" type="datetime-local" label="Publication des résultats" :value="$dt($challenge->results_at)" required hint="Le classement devient visible et la notation se ferme." />
            </div>

            @if($scored)
                <p class="hint" style="margin-top:14px"><strong>Des notes ont déjà été saisies :</strong> la structure du barème est figée, seuls les intitulés et les descriptions peuvent changer.</p>
            @endif

            @foreach(\App\Models\Challenge::KINDS as $kind => $kindLabel)
                @php
                    $rows = old('criteria.'.$kind, $challenge->criteriaOf($kind)->map(fn ($c) => ['id' => $c->id, 'label' => $c->label, 'description' => $c->description, 'max_points' => $c->max_points])->all());
                    $rows = array_values((array) $rows);
                    $rows = $scored ? $rows : array_pad($rows, max(3, count($rows) + 1), ['id' => '', 'label' => '', 'description' => '', 'max_points' => '']);
                @endphp
                <h3>Barème — {{ $kindLabel }}</h3>
                <p class="text-sm text-muted">
                    @if($kind === 'theme')Critères et points maximum pour noter une activité qui traite la thématique (obligatoire pour publier).
                    @else Barème distinct pour les activités hors thématique. Sans critère, les clubs ne peuvent pas en proposer.@endif
                </p>
                <div class="field {{ $errors->has('criteria.'.$kind) ? 'has-error' : '' }}">
                    <div data-rows="{{ $kind }}">
                        @foreach($rows as $i => $row)
                            <div class="form-grid" style="margin-top:8px" data-row>
                                <input type="hidden" name="criteria[{{ $kind }}][{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                                <div class="field"><input type="text" name="criteria[{{ $kind }}][{{ $i }}][label]" maxlength="160" placeholder="Critère (ex. Impact sur les participants)" aria-label="Critère" value="{{ $row['label'] ?? '' }}"></div>
                                <div class="field"><input type="number" name="criteria[{{ $kind }}][{{ $i }}][max_points]" min="1" max="1000" placeholder="Points max" aria-label="Points maximum" value="{{ $row['max_points'] ?? '' }}" @readonly($scored)></div>
                                <div class="field span-2"><input type="text" name="criteria[{{ $kind }}][{{ $i }}][description]" maxlength="400" placeholder="Ce que le comité regarde (facultatif)" aria-label="Description du critère" value="{{ $row['description'] ?? '' }}"></div>
                            </div>
                        @endforeach
                    </div>
                    @unless($scored)<button type="button" class="btn btn-secondary btn-sm" data-add-row="{{ $kind }}" style="margin-top:8px">+ Ajouter un critère</button>@endunless
                    @error('criteria.'.$kind)<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach

            <h3>Comité d’évaluation</h3>
            <div class="field {{ $errors->has('committee_emails') ? 'has-error' : '' }}">
                <label for="f-committee">Adresses e-mail des membres du comité</label>
                <textarea id="f-committee" name="committee_emails" rows="4" maxlength="5000" placeholder="une adresse par ligne">{{ old('committee_emails', $challenge->committee->pluck('email')->implode("\n")) }}</textarea>
                <p class="hint">Chaque membre doit avoir un compte T.Link. Il note les activités depuis la page « Défis » (jamais celles de son propre club).</p>
                @error('committee_emails')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ $editing ? route('admin.challenges.show', $challenge) : route('admin.challenges.index') }}" class="btn btn-ghost">Annuler</a>
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $editing ? 'Enregistrer' : 'Créer le défi' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-add-row]').forEach(function (button) {
        button.addEventListener('click', function () {
            var kind = button.getAttribute('data-add-row');
            var box = document.querySelector('[data-rows="' + kind + '"]');
            var rows = box.querySelectorAll('[data-row]');
            var clone = rows[rows.length - 1].cloneNode(true);
            clone.querySelectorAll('input').forEach(function (input) {
                input.value = '';
                input.name = input.name.replace(/\[\d+\]/, '[' + rows.length + ']');
            });
            box.appendChild(clone);
        });
    });
</script>
@endpush
