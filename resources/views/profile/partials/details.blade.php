{{-- Parcours du membre : expériences, formations, certifications et engagements associatifs (logos inclus), en frise chronologique. --}}
@php
    $editButton = 'inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50';
    $dot = 'absolute -left-[1.72rem] top-5 h-3 w-3 rounded-full border-2 border-white bg-tlink-blue';
    $blocks = [
        ['title' => 'Experience pro', 'icon' => 'fa-solid fa-briefcase', 'items' => $professionalExperiences, 'auto' => collect(), 'modal' => 'modal-professional', 'empty' => 'Aucune experience professionnelle ajoutee.', 'kind' => 'pro'],
        ['title' => 'Formation', 'icon' => 'fa-solid fa-graduation-cap', 'items' => $educations, 'auto' => collect(), 'modal' => 'modal-education', 'empty' => 'Aucune formation ajoutee.', 'kind' => 'edu'],
        ['title' => 'Certification', 'icon' => 'fa-solid fa-certificate', 'items' => $certifications, 'auto' => $academyCertificates ?? collect(), 'modal' => 'modal-certification', 'empty' => 'Aucune certification ajoutee.', 'kind' => 'cert'],
        ['title' => 'Experience associatif', 'icon' => 'fa-solid fa-people-group', 'items' => $associativeExperiences, 'auto' => $foundationMemberships ?? collect(), 'modal' => 'modal-associative', 'empty' => 'Aucune experience associative ajoutee.', 'kind' => 'asso'],
    ];
@endphp

<section id="parcours" class="scroll-mt-20 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
    <h2 class="font-display text-xl font-semibold text-slate-900">Parcours</h2>

    <div class="mt-5 space-y-8">
        @foreach($blocks as $block)
            @if($editable || $block['items']->isNotEmpty() || $block['auto']->isNotEmpty())
                <div>
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="flex items-center gap-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-tlink-blue"><i class="{{ $block['icon'] }}"></i></span>
                            {{ $block['title'] }}
                        </h3>
                        @if($editable)<button type="button" class="{{ $editButton }}" onclick="openProfileModal('{{ $block['modal'] }}')"><i class="fa-solid fa-pen text-[10px]"></i> Modifier</button>@endif
                    </div>

                    <div class="mt-4 space-y-4 border-l-2 border-slate-200 pl-5">
                        @foreach($block['auto'] as $auto)
                            @if($block['kind'] === 'cert')
                                <article class="relative flex gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/50 px-4 py-3">
                                    <span class="{{ $dot }}"></span>
                                    <x-org-logo :url="$auto['logo_url'] ?? null" :name="$auto['issuer']" icon="fa-solid fa-graduation-cap" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-900">{{ $auto['name'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $auto['issuer'] }} · {{ $auto['date_obtained'] }}</p>
                                        <p class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Obtenu via Tunivisions Academy</span>
                                            <a href="{{ $auto['url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-sky-700 hover:underline">Voir le certificat <span class="text-slate-400">({{ $auto['code'] }})</span></a>
                                        </p>
                                    </div>
                                </article>
                            @else
                                <article class="relative flex gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/50 px-4 py-3" data-logo="{{ $auto['logo_url'] ?? '' }}">
                                    <span class="{{ $dot }}"></span>
                                    <x-org-logo :url="$auto['logo_url'] ?? null" :name="$auto['club_name'] ?? ''" icon="fa-solid fa-people-group" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-900">{{ $auto['role'] }} · {{ $auto['club_name'] }}</p>
                                        @if(! empty($auto['department']))<p class="mt-1 text-xs text-slate-500">{{ $auto['department'] }}</p>@endif
                                        @if(! empty($auto['info']))<p class="mt-1 text-xs text-slate-500">{{ $auto['info'] }}</p>@endif
                                        <p class="mt-1 text-xs text-slate-500">@if($auto['mandate'])Mandat {{ $auto['mandate'] }}@else{{ $auto['start_date'] ?? '-' }} - {{ ($auto['end_date'] ?? '') !== '' ? $auto['end_date'] : 'En cours' }}@endif</p>
                                        <p class="mt-2"><span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Adhésion validée Tunivisions</span></p>
                                    </div>
                                </article>
                            @endif
                        @endforeach
                        @foreach ($block['items'] as $item)
                            @if($block['kind'] === 'pro')
                                <article class="relative flex gap-3 rounded-2xl bg-slate-50 px-4 py-3" data-logo="{{ $item['logo_url'] ?? '' }}">
                                    <span class="{{ $dot }}"></span>
                                    <x-org-logo :url="$item['logo_url'] ?? null" :name="$item['company'] ?? ''" icon="fa-solid fa-building" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-900">{{ $item['role'] ?? 'Poste' }} · {{ $item['company'] ?? 'Societe' }}</p>
                                        @if(! empty($item['url']))<a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-xs text-sky-700 hover:underline">{{ $item['url'] }}</a>@endif
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['start_date'] ?? '-' }} - {{ ($item['end_date'] ?? '') !== '' ? $item['end_date'] : 'En cours' }}</p>
                                        @if(($item['description'] ?? '') !== '')<p class="mt-2 text-sm text-slate-700">{{ $item['description'] }}</p>@endif
                                    </div>
                                </article>
                            @elseif($block['kind'] === 'edu')
                                <article class="relative flex gap-3 rounded-2xl bg-slate-50 px-4 py-3" data-logo="{{ $item['logo_url'] ?? '' }}">
                                    <span class="{{ $dot }}"></span>
                                    <x-org-logo :url="$item['logo_url'] ?? null" :name="$item['school'] ?? ''" icon="fa-solid fa-graduation-cap" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-900">{{ $item['degree_type'] ?? 'Diplome' }} · {{ $item['diploma_name'] ?? 'Intitule' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['school'] ?? 'Ecole' }}</p>
                                        @if(! empty($item['info']))<p class="mt-1 text-xs text-slate-500">{{ $item['info'] }}</p>@endif
                                        @if(! empty($item['url']))<a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-xs text-sky-700 hover:underline">{{ $item['url'] }}</a>@endif
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['start_date'] ?? '-' }} - {{ ($item['end_date'] ?? '') !== '' ? $item['end_date'] : 'En cours' }}</p>
                                    </div>
                                </article>
                            @elseif($block['kind'] === 'cert')
                                <article class="relative rounded-2xl bg-slate-50 px-4 py-3">
                                    <span class="{{ $dot }}"></span>
                                    <p class="text-sm font-semibold text-slate-900">{{ $item['name'] ?? 'Certification' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $item['issuer'] ?? 'Organisme' }} · {{ $item['date_obtained'] ?? '-' }}</p>
                                    <p class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                                        <span class="rounded-full bg-slate-200 px-2 py-0.5 font-semibold text-slate-700">Externe</span>
                                        @if(preg_match('#^https?://#i', (string) ($item['url'] ?? '')) === 1)<a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="font-semibold text-sky-700 hover:underline">Voir la certification</a>@endif
                                    </p>
                                </article>
                            @else
                                <article class="relative flex gap-3 rounded-2xl bg-slate-50 px-4 py-3" data-logo="{{ $item['logo_url'] ?? '' }}">
                                    <span class="{{ $dot }}"></span>
                                    <x-org-logo :url="$item['logo_url'] ?? null" :name="$item['club_name'] ?? ''" icon="fa-solid fa-people-group" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-900">{{ $item['role'] ?? 'Poste' }} · {{ $item['club_name'] ?? 'Club' }}</p>
                                        @if(($item['department'] ?? '') !== '')<p class="mt-1 text-xs text-slate-500">{{ $item['department'] }}</p>@endif
                                        @if(! empty($item['info']))<p class="mt-1 text-xs text-slate-500">{{ $item['info'] }}</p>@endif
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['start_date'] ?? '-' }} - {{ ($item['end_date'] ?? '') !== '' ? $item['end_date'] : 'En cours' }}</p>
                                        @if(($item['description'] ?? '') !== '')<p class="mt-2 text-sm text-slate-700">{{ $item['description'] }}</p>@endif
                                        <p class="mt-2"><span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">Externe</span></p>
                                    </div>
                                </article>
                            @endif
                        @endforeach
                        @if($block['items']->isEmpty() && $block['auto']->isEmpty())
                            <p class="text-sm text-slate-500">{{ $block['empty'] }}@if($editable && $block['kind'] === 'asso') Vos adhésions validées apparaîtront ici automatiquement : <a href="{{ route('memberships.index') }}" class="font-semibold text-sky-700 hover:underline">demander une adhésion</a>.@elseif($editable && $block['kind'] === 'cert') Vos certificats Tunivisions Academy apparaîtront ici automatiquement : <a href="{{ route('academy.index') }}" class="font-semibold text-sky-700 hover:underline">voir les cours</a>.@endif</p>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>
