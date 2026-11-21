@extends('admin.layouts.app')

@section('title', 'Sponsors & partenaires — '.$event->title)
@section('section', 'Événements')

@php
    // Valeur d'un champ : l'ancienne saisie uniquement pour la modale qui vient d'échouer.
    $o = fn (string $form, string $key, $default = null) => old('_form') === $form ? old($key, $default) : $default;
    $total = $levels->sum(fn ($level) => $level->sponsors->count());
@endphp

@section('content')
    @include('admin.events.manage._header')

    <div class="tr-toolbar">
        <p class="text-sm text-muted">
            {{ $total }} sponsor(s) et partenaire(s) répartis en {{ $levels->count() }} niveau(x). Le rang 1 est affiché en premier sur la page de l’événement.
        </p>
        <div class="inline-form">
            <button type="button" class="btn btn-secondary" data-modal-open="level-create"><x-admin.icon name="plus" :size="16" /> Nouveau niveau</button>
            @if($levels->isNotEmpty())
                <button type="button" class="btn btn-primary" data-modal-open="sponsor-create"><x-admin.icon name="plus" :size="16" /> Sponsor / partenaire</button>
            @endif
        </div>
    </div>

    @forelse($levels as $level)
        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head">
                <div>
                    <h2>{{ $level->name }} <span class="badge badge-info">{{ $level->kindLabel() }}</span></h2>
                    <div class="sub">Rang {{ $level->rank }} · {{ $level->sponsors->count() }} élément(s)</div>
                </div>
                <div class="inline-form">
                    <button type="button" class="btn btn-secondary btn-sm" data-modal-open="sponsor-create" data-sponsor-level="{{ $level->id }}"><x-admin.icon name="plus" :size="14" /> Ajouter</button>
                    <button type="button" class="btn btn-ghost btn-icon btn-sm" data-modal-open="level-edit-{{ $level->id }}" aria-label="Modifier le niveau"><x-admin.icon name="edit" :size="14" /></button>
                    <form method="post" action="{{ route('admin.events.sponsors.levels.destroy', [$event, $level]) }}" data-confirm="Supprimer le niveau « {{ $level->name }} » ?" data-confirm-title="Supprimer le niveau">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Supprimer le niveau"><x-admin.icon name="trash" :size="14" /></button>
                    </form>
                </div>
            </div>

            @if($level->sponsors->isEmpty())
                <p class="card-body text-muted">Aucun sponsor ni partenaire dans ce niveau.</p>
            @else
                <div class="sp-grid">
                    @foreach($level->sponsors as $sponsor)
                        <div class="sp-tile">
                            <div class="sp-logo">
                                @if($sponsor->logoUrl())<img src="{{ $sponsor->logoUrl() }}" alt="" loading="lazy">@else<span>{{ mb_strtoupper(mb_substr($sponsor->name, 0, 1)) }}</span>@endif
                            </div>
                            <div class="sp-info">
                                <strong>{{ $sponsor->name }}</strong>
                                @if($sponsor->website_url)<a href="{{ $sponsor->website_url }}" target="_blank" rel="noopener noreferrer" class="text-sm">{{ preg_replace('#^https?://(www\.)?#i', '', rtrim($sponsor->website_url, '/')) }}</a>@endif
                                <span class="badge badge-neutral">{{ $sponsor->directory_sponsor_id ? 'Carnet de partenaires' : ($sponsor->directory_partner_id ? 'Partenaire du site' : 'Saisi à la main') }}</span>
                            </div>
                            <div class="sp-actions">
                                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-modal-open="sponsor-edit-{{ $sponsor->id }}" aria-label="Modifier"><x-admin.icon name="edit" :size="14" /></button>
                                <form method="post" action="{{ route('admin.events.sponsors.destroy', [$event, $sponsor]) }}" data-confirm="Retirer « {{ $sponsor->name }} » de l’événement ?" data-confirm-title="Retirer le sponsor">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Supprimer"><x-admin.icon name="trash" :size="14" /></button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    @empty
        <article class="card card-flush">
            <x-admin.empty icon="flag" title="Aucun niveau" text="Commencez par créer vos niveaux (Platinum, Gold, Partenaire média…), puis ajoutez-y vos sponsors et partenaires." />
        </article>
    @endforelse

    {{-- ===================== Modales ===================== --}}
    <x-admin.modal id="level-create" title="Nouveau niveau" :action="route('admin.events.sponsors.levels.store', $event)" submit="Ajouter le niveau" icon="plus">
        <div class="field" style="grid-column:1/-1"><label>Nom <span class="req">*</span></label><input name="name" required maxlength="120" value="{{ $o('level-create', 'name') }}" placeholder="Platinum"></div>
        <div class="field">
            <label>Type</label>
            <select name="kind">@foreach(\App\Models\EventSponsorLevel::KINDS as $code => $label)<option value="{{ $code }}" @selected($o('level-create', 'kind') === $code)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="field"><label>Rang <span class="req">*</span></label><input type="number" name="rank" min="1" max="99" required value="{{ $o('level-create', 'rank', $levels->count() + 1) }}"></div>
    </x-admin.modal>

    @foreach($levels as $level)
        <x-admin.modal :id="'level-edit-'.$level->id" :title="'Modifier le niveau — '.$level->name" :action="route('admin.events.sponsors.levels.update', [$event, $level])" method="PUT">
            @php($form = 'level-edit-'.$level->id)
            <div class="field" style="grid-column:1/-1"><label>Nom <span class="req">*</span></label><input name="name" required maxlength="120" value="{{ $o($form, 'name', $level->name) }}"></div>
            <div class="field">
                <label>Type</label>
                <select name="kind">@foreach(\App\Models\EventSponsorLevel::KINDS as $code => $label)<option value="{{ $code }}" @selected($o($form, 'kind', $level->kind) === $code)>{{ $label }}</option>@endforeach</select>
            </div>
            <div class="field"><label>Rang <span class="req">*</span></label><input type="number" name="rank" min="1" max="99" required value="{{ $o($form, 'rank', $level->rank) }}"></div>
        </x-admin.modal>
    @endforeach

    @if($levels->isNotEmpty())
        <x-admin.modal id="sponsor-create" title="Nouveau sponsor / partenaire" :action="route('admin.events.sponsors.store', $event)" submit="Ajouter" icon="plus" :files="true">
            @include('admin.events.manage._sponsor_fields', ['formId' => 'sponsor-create', 'sponsor' => null])
        </x-admin.modal>

        @foreach($levels as $level)
            @foreach($level->sponsors as $sponsor)
                <x-admin.modal :id="'sponsor-edit-'.$sponsor->id" :title="'Modifier — '.$sponsor->name" :action="route('admin.events.sponsors.update', [$event, $sponsor])" method="PUT" :files="true">
                    @include('admin.events.manage._sponsor_fields', ['formId' => 'sponsor-edit-'.$sponsor->id, 'sponsor' => $sponsor])
                </x-admin.modal>
            @endforeach
        @endforeach
    @endif
@endsection

@push('scripts')
<script>
    // Le bouton « Ajouter » d'un niveau présélectionne ce niveau dans la modale d'ajout.
    document.querySelectorAll('[data-sponsor-level]').forEach(function (button) {
        button.addEventListener('click', function () {
            var select = document.querySelector('#sponsor-create [data-sponsor-level-select]');
            if (select) { select.value = button.getAttribute('data-sponsor-level'); }
        });
    });
</script>
@endpush
