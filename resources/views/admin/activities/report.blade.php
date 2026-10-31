@extends('admin.layouts.app')

@section('title', 'Compte rendu — '.$activity->title)
@section('section', 'Défis')

@php
    $money = fn ($value) => $value === null ? '—' : number_format((float) $value, 3, ',', ' ').' DT';
    $tone = ['draft' => 'neutral', 'pending' => 'warning', 'validated' => 'success'][$report->status] ?? 'neutral';
    $tabs = [
        'bilan' => 'Bilan',
        'auto' => 'Auto-évaluation',
        'rh' => 'Ressources humaines',
        'marketing' => 'Marketing',
        'budget' => 'Sponsoring et budget',
        'avis' => 'Avis des participants',
    ];
@endphp

@section('content')
    <x-admin.page-header :title="'Compte rendu — '.$activity->title" :subtitle="$activity->challenge->title.' · '.$activity->club->title.' · '.$activity->starts_at->format('d/m/Y')">
        <x-slot:actions>
            <a href="{{ route('admin.activities.show', $activity) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour à l’activité</a>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card">
        <div class="card-body text-sm">
            <p><strong>Statut :</strong> <span class="badge badge-{{ $tone }}">{{ $report->statusLabel() }}</span></p>
            <p class="text-muted">Le club rédige le compte rendu, le président du club porteur le valide, puis il est transmis au comité d’évaluation du défi. Sans compte rendu validé, l’activité ne peut pas être notée.</p>
            @if($report->status === 'draft' && $report->president_comment)
                <p style="margin-top:10px"><strong>Le président demande des corrections :</strong> {{ $report->president_comment }}</p>
            @endif
            @if($report->submitted_at)<p>Soumis le {{ $report->submitted_at->format('d/m/Y à H:i') }}@if($report->submitter) par {{ $report->submitter->name }}@endif.</p>@endif
            @if($report->validated_at)<p>Validé le {{ $report->validated_at->format('d/m/Y à H:i') }}@if($report->validator) par {{ $report->validator->name }}@endif.</p>@endif
        </div>
    </article>

    @if($editable)
        <form method="post" action="{{ route('admin.activities.report.update', $activity) }}" class="card card-flush" data-tabs-root>
            @csrf @method('PUT')
    @else
        <div class="card card-flush" data-tabs-root>
    @endif

        <div class="tabs" role="tablist" aria-label="Parties du compte rendu">
            @foreach($tabs as $key => $label)
                <button type="button" class="tab {{ $loop->first ? 'is-active' : '' }}" role="tab" id="tab-btn-{{ $key }}" data-tab="{{ $key }}" aria-controls="tab-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="card-body">
            @foreach($tabs as $key => $label)
                <section id="tab-{{ $key }}" class="tab-panel" role="tabpanel" aria-labelledby="tab-btn-{{ $key }}" @if(! $loop->first) hidden @endif>
                    @include('admin.activities.partials.report-'.$key)
                </section>
            @endforeach
        </div>

        @if($editable)
            <div class="form-actions">
                <button type="submit" name="action" value="draft" class="btn btn-secondary">Enregistrer le brouillon</button>
                <button type="submit" name="action" value="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Soumettre au président</button>
            </div>
    @endif

    @if($editable)
        </form>
    @else
        </div>
    @endif

    @unless($editable)
        @if($canValidate)
            <article class="card">
                <div class="card-head"><div><h2>Décision du président</h2><div class="sub">Validez pour transmettre le compte rendu au comité d’évaluation, ou renvoyez-le au club avec vos remarques.</div></div></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.activities.report.approve', $activity) }}" class="inline-form" style="margin-bottom:16px">
                        @csrf
                        <button type="submit" class="btn btn-success"><x-admin.icon name="check" :size="16" /> Valider et transmettre au comité</button>
                    </form>
                    <form method="post" action="{{ route('admin.activities.report.return', $activity) }}">
                        @csrf
                        <div class="field {{ $errors->has('president_comment') ? 'has-error' : '' }}">
                            <label for="f-president_comment">Corrections demandées</label>
                            <textarea id="f-president_comment" name="president_comment" rows="3" maxlength="2000" required>{{ old('president_comment') }}</textarea>
                            @error('president_comment')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="btn btn-secondary" style="margin-top:8px">Renvoyer au club</button>
                    </form>
                </div>
            </article>
        @elseif($report->status === 'pending')
            <p class="text-sm text-muted">En attente de la validation du président du club porteur.</p>
        @endif
    @endunless
@endsection

@push('scripts')
<script>
    (function () {
        var root = document.querySelector('[data-tabs-root]');
        if (!root) return;
        var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-tab]'));

        function show(key, updateHash) {
            buttons.forEach(function (button) {
                var on = button.getAttribute('data-tab') === key;
                button.classList.toggle('is-active', on);
                button.setAttribute('aria-selected', on ? 'true' : 'false');
                document.getElementById('tab-' + button.getAttribute('data-tab')).hidden = !on;
            });
            if (updateHash && window.history && history.replaceState) history.replaceState(null, '', '#' + key);
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () { show(button.getAttribute('data-tab'), true); });
        });

        // Les onglets qui contiennent une erreur sont signalés ; le premier s'ouvre à l'arrivée.
        var firstError = null;
        buttons.forEach(function (button) {
            var panel = document.getElementById('tab-' + button.getAttribute('data-tab'));
            if (panel.querySelector('.has-error, .error')) {
                button.style.color = 'var(--danger)';
                button.insertAdjacentHTML('beforeend', ' <span title="À corriger">●</span>');
                firstError = firstError || button.getAttribute('data-tab');
            }
        });

        var hash = (location.hash || '').replace('#', '');
        show(firstError || (buttons.some(function (b) { return b.getAttribute('data-tab') === hash; }) ? hash : buttons[0].getAttribute('data-tab')), false);

        // Ajout de lignes (membres, retombées médias, partenaires).
        root.querySelectorAll('[data-add-row]').forEach(function (button) {
            button.addEventListener('click', function () {
                var box = root.querySelector('[data-rows="' + button.getAttribute('data-add-row') + '"]');
                var rows = box.querySelectorAll('[data-row]');
                var clone = rows[rows.length - 1].cloneNode(true);
                clone.querySelectorAll('input, select').forEach(function (field) {
                    if (field.tagName === 'SELECT') { field.selectedIndex = 0; } else { field.value = ''; }
                    field.name = field.name.replace(/\[\d+\]/, '[' + rows.length + ']');
                });
                box.appendChild(clone);
            });
        });
    })();
</script>
@endpush
