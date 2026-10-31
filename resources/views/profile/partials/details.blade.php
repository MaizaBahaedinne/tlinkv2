{{-- Parcours du membre : expériences, formations, certifications et engagements associatifs (logos inclus), en frise chronologique. --}}
@php
    $editButton = 'inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50';
    $dot = 'absolute -left-[1.72rem] top-5 h-3 w-3 rounded-full border-2 border-white bg-tlink-blue';
    $blocks = [
        ['title' => 'Experience pro', 'icon' => 'fa-solid fa-briefcase', 'items' => $professionalExperiences, 'modal' => 'modal-professional', 'empty' => 'Aucune experience professionnelle ajoutee.', 'kind' => 'pro'],
        ['title' => 'Formation', 'icon' => 'fa-solid fa-graduation-cap', 'items' => $educations, 'modal' => 'modal-education', 'empty' => 'Aucune formation ajoutee.', 'kind' => 'edu'],
        ['title' => 'Certification', 'icon' => 'fa-solid fa-certificate', 'items' => $certifications, 'modal' => 'modal-certification', 'empty' => 'Aucune certification ajoutee.', 'kind' => 'cert'],
        ['title' => 'Experience associatif', 'icon' => 'fa-solid fa-people-group', 'items' => $associativeExperiences, 'modal' => 'modal-associative', 'empty' => 'Aucune experience associative ajoutee.', 'kind' => 'asso'],
    ];
@endphp

<section id="parcours" class="scroll-mt-20 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
    <h2 class="font-display text-xl font-semibold text-slate-900">Parcours</h2>

    <div class="mt-5 space-y-8">
        @foreach($blocks as $block)
            @if($editable || $block['items']->isNotEmpty())
                <div>
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="flex items-center gap-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-tlink-blue"><i class="{{ $block['icon'] }}"></i></span>
                            {{ $block['title'] }}
                        </h3>
                        @if($editable)<button type="button" class="{{ $editButton }}" onclick="openProfileModal('{{ $block['modal'] }}')"><i class="fa-solid fa-pen text-[10px]"></i> Modifier</button>@endif
                    </div>

                    <div class="mt-4 space-y-4 border-l-2 border-slate-200 pl-5">
                        @forelse ($block['items'] as $item)
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
                                    </div>
                                </article>
                            @endif
                        @empty
                            <p class="text-sm text-slate-500">{{ $block['empty'] }}</p>
                        @endforelse
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>
