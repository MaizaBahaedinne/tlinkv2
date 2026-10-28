{{-- Parcours du membre : expériences, formations, certifications et engagements associatifs (logos inclus). --}}
@php
    $sectionClass = 'rounded-3xl border border-slate-200 bg-white p-6 shadow-soft';
    $editButton = 'rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50';
@endphp

@if($editable || $professionalExperiences->isNotEmpty())
    <section class="{{ $sectionClass }}">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-display text-xl font-semibold text-slate-900">Experience pro</h2>
            @if($editable)<button type="button" class="{{ $editButton }}" onclick="openProfileModal('modal-professional')">Modifier</button>@endif
        </div>
        <div class="mt-4 space-y-3">
            @forelse ($professionalExperiences as $item)
                <article class="flex gap-3 rounded-2xl border border-slate-200 px-4 py-3" data-logo="{{ $item['logo_url'] ?? '' }}">
                    <x-org-logo :url="$item['logo_url'] ?? null" :name="$item['company'] ?? ''" icon="fa-solid fa-building" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">{{ $item['role'] ?? 'Poste' }} · {{ $item['company'] ?? 'Societe' }}</p>
                        @if(! empty($item['url']))<a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-xs text-sky-700 hover:underline">{{ $item['url'] }}</a>@endif
                        <p class="mt-1 text-xs text-slate-500">{{ $item['start_date'] ?? '-' }} - {{ ($item['end_date'] ?? '') !== '' ? $item['end_date'] : 'En cours' }}</p>
                        @if(($item['description'] ?? '') !== '')<p class="mt-2 text-sm text-slate-700">{{ $item['description'] }}</p>@endif
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-500">Aucune experience professionnelle ajoutee.</p>
            @endforelse
        </div>
    </section>
@endif

@if($editable || $educations->isNotEmpty())
    <section class="{{ $sectionClass }}">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-display text-xl font-semibold text-slate-900">Formation</h2>
            @if($editable)<button type="button" class="{{ $editButton }}" onclick="openProfileModal('modal-education')">Modifier</button>@endif
        </div>
        <div class="mt-4 space-y-3">
            @forelse ($educations as $item)
                <article class="flex gap-3 rounded-2xl border border-slate-200 px-4 py-3" data-logo="{{ $item['logo_url'] ?? '' }}">
                    <x-org-logo :url="$item['logo_url'] ?? null" :name="$item['school'] ?? ''" icon="fa-solid fa-graduation-cap" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">{{ $item['degree_type'] ?? 'Diplome' }} · {{ $item['diploma_name'] ?? 'Intitule' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $item['school'] ?? 'Ecole' }}</p>
                        @if(! empty($item['info']))<p class="mt-1 text-xs text-slate-500">{{ $item['info'] }}</p>@endif
                        @if(! empty($item['url']))<a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-xs text-sky-700 hover:underline">{{ $item['url'] }}</a>@endif
                        <p class="mt-1 text-xs text-slate-500">{{ $item['start_date'] ?? '-' }} - {{ ($item['end_date'] ?? '') !== '' ? $item['end_date'] : 'En cours' }}</p>
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-500">Aucune formation ajoutee.</p>
            @endforelse
        </div>
    </section>
@endif

@if($editable || $certifications->isNotEmpty())
    <section class="{{ $sectionClass }}">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-display text-xl font-semibold text-slate-900">Certification</h2>
            @if($editable)<button type="button" class="{{ $editButton }}" onclick="openProfileModal('modal-certification')">Modifier</button>@endif
        </div>
        <div class="mt-4 space-y-3">
            @forelse ($certifications as $item)
                <article class="rounded-2xl border border-slate-200 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-900">{{ $item['name'] ?? 'Certification' }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $item['issuer'] ?? 'Organisme' }} · {{ $item['date_obtained'] ?? '-' }}</p>
                </article>
            @empty
                <p class="text-sm text-slate-500">Aucune certification ajoutee.</p>
            @endforelse
        </div>
    </section>
@endif

@if($editable || $associativeExperiences->isNotEmpty())
    <section class="{{ $sectionClass }}">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-display text-xl font-semibold text-slate-900">Experience associatif</h2>
            @if($editable)<button type="button" class="{{ $editButton }}" onclick="openProfileModal('modal-associative')">Modifier</button>@endif
        </div>
        <div class="mt-4 space-y-3">
            @forelse ($associativeExperiences as $item)
                <article class="flex gap-3 rounded-2xl border border-slate-200 px-4 py-3" data-logo="{{ $item['logo_url'] ?? '' }}">
                    <x-org-logo :url="$item['logo_url'] ?? null" :name="$item['club_name'] ?? ''" icon="fa-solid fa-people-group" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">{{ $item['role'] ?? 'Poste' }} · {{ $item['club_name'] ?? 'Club' }}</p>
                        @if(($item['department'] ?? '') !== '')<p class="mt-1 text-xs text-slate-500">{{ $item['department'] }}</p>@endif
                        @if(! empty($item['info']))<p class="mt-1 text-xs text-slate-500">{{ $item['info'] }}</p>@endif
                        <p class="mt-1 text-xs text-slate-500">{{ $item['start_date'] ?? '-' }} - {{ ($item['end_date'] ?? '') !== '' ? $item['end_date'] : 'En cours' }}</p>
                        @if(($item['description'] ?? '') !== '')<p class="mt-2 text-sm text-slate-700">{{ $item['description'] }}</p>@endif
                    </div>
                </article>
            @empty
                <p class="text-sm text-slate-500">Aucune experience associative ajoutee.</p>
            @endforelse
        </div>
    </section>
@endif
