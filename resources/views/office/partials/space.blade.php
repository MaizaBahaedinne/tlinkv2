@php
    $fmt = fn ($date) => $date ? $date->translatedFormat('d M Y') : null;
    $siteLocale = array_key_exists(app()->getLocale(), config('site.locales')) ? app()->getLocale() : config('site.default_locale');
    $eventUrl = fn ($event) => in_array($event->status, ['published', 'completed'], true) ? route('site.events.show', ['locale' => $siteLocale, 'event' => $event->id]) : null;
@endphp

{{-- Adhésions et mandats en cours --}}
<section id="en-cours" role="tabpanel" aria-labelledby="tab-en-cours" data-tab-panel class="grid gap-4 md:grid-cols-2">
    <div class="card p-5">
        <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-id-card mr-2 text-slate-400"></i>Mes adhésions en cours</h2>
        <ul class="mt-3 space-y-2">
            @forelse($space['memberships'] as $row)
                <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">{{ $row['place'] }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <span class="rounded-full px-2 py-0.5 font-medium {{ $row['membership']->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $row['label'] }}</span>
                        @if($scope = \App\Support\OrgScopes::label($row['membership']->scope))<span>{{ $scope }}</span>@endif
                        @if($since = $fmt($row['membership']->starts_at))<span>depuis le {{ $since }}</span>@endif
                        @if($until = $fmt($row['membership']->ends_at))<span>jusqu’au {{ $until }}</span>@endif
                    </p>
                </li>
            @empty
                <li class="text-sm text-slate-500">Aucune adhésion en cours.</li>
            @endforelse
        </ul>
    </div>

    <div class="card p-5">
        <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-briefcase mr-2 text-slate-400"></i>Mes mandats en cours</h2>
        <ul class="mt-3 space-y-2">
            @forelse($space['mandates'] as $row)
                <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">{{ $row['node']->title }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        @if($row['club'])<span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">{{ $row['club']->title }}</span>@endif
                        @if($since = $fmt($row['assignment']->starts_at))<span>depuis le {{ $since }}</span>@endif
                        @if($until = $fmt($row['assignment']->ends_at))<span>jusqu’au {{ $until }}</span>@endif
                    </p>
                </li>
            @empty
                <li class="text-sm text-slate-500">Aucun mandat en cours.</li>
            @endforelse
        </ul>
    </div>
</section>

{{-- Mes clubs et leur hiérarchie --}}
<section id="clubs" role="tabpanel" aria-labelledby="tab-clubs" data-tab-panel class="space-y-4">
    <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-people-group mr-2 text-slate-400"></i>Mes clubs</h2>

    @forelse($space['clubs'] as $club)
        <article class="card overflow-hidden">
            <div class="border-b border-slate-100 bg-white p-5">
                <h3 class="font-display text-xl font-semibold text-slate-900">{{ $club['node']->title }}</h3>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    @if($club['governorate'])<span><i class="fa-solid fa-location-dot mr-1"></i>{{ $club['governorate'] }}</span>@endif
                    @if($club['membership'])<span class="rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700">{{ $club['membership'] }}</span>@endif
                    @foreach($club['my_roles'] as $role)<span class="rounded-full bg-rose-50 px-2 py-0.5 font-medium text-rose-700">{{ $role }}</span>@endforeach
                </p>
            </div>

            <div class="p-5">
                <h4 class="mb-3 text-sm font-semibold text-slate-800"><i class="fa-solid fa-sitemap mr-2 text-slate-400"></i>Hiérarchie du club</h4>
                @forelse($club['bureau'] as $row)
                    @php
                        $rowNode = $row['node'];
                    @endphp
                    <div class="mb-2" style="margin-inline-start: {{ min($row['depth'], 6) * 1.25 }}rem">
                        @if($rowNode->kind === 'position')
                            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                <p class="text-sm font-semibold text-slate-800">{{ $rowNode->title }}</p>
                                @if($row['holders'] === [])
                                    <p class="text-xs text-slate-500">Poste vacant.</p>
                                @else
                                    <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                        @foreach($row['holders'] as $holder)
                                            <li>@include('office.partials.holder', ['holder' => $holder])@if($holder['is_me'])<span class="ml-2 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">C’est moi</span>@endif</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @else
                            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $rowNode->title }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Le bureau de ce club n’est pas encore renseigné.</p>
                @endforelse
            </div>
        </article>
    @empty
        <div class="card p-5 text-sm text-slate-500">Vous n’êtes rattaché(e) à aucun club pour le moment.</div>
    @endforelse
</section>

{{-- Événements --}}
<section id="evenements" role="tabpanel" aria-labelledby="tab-evenements" data-tab-panel class="space-y-4">
    <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-calendar-days mr-2 text-slate-400"></i>Événements</h2>

    @foreach([['À venir', $space['upcoming'], 'Aucun événement à venir.'], ['Passés', $space['past'], 'Aucun événement passé.']] as [$heading, $events, $empty])
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-slate-800">{{ $heading }}</h3>
            <ul class="mt-3 divide-y divide-slate-100">
                @forelse($events as $event)
                    @php
                        $registration = \App\Support\MyOfficeSpace::registrationLabel($space['registrations'][$event->id] ?? null);
                    @endphp
                    <li class="flex items-center gap-4 py-3">
                        <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-tlink-blue text-white">
                            <span class="text-base font-bold leading-none">{{ $event->starts_at->format('d') }}</span>
                            <span class="mt-0.5 text-[10px] uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            @if($url = $eventUrl($event))
                                <a href="{{ $url }}" class="text-sm font-semibold text-slate-800 hover:underline">{{ $event->title }}</a>
                            @else
                                <p class="text-sm font-semibold text-slate-800">{{ $event->title }}</p>
                            @endif
                            <p class="text-xs text-slate-500">{{ $event->starts_at->translatedFormat('d F Y, H:i') }}@if($place = collect([$event->location, $event->governorate?->name])->filter()->implode(' · ')) · {{ $place }}@endif</p>
                        </div>
                        @if($registration)
                            <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">{{ $registration }}</span>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-sm text-slate-500">{{ $empty }}</li>
                @endforelse
            </ul>
        </div>
    @endforeach
</section>

{{-- Parcours --}}
<section id="parcours" role="tabpanel" aria-labelledby="tab-parcours" data-tab-panel class="card p-5">
    <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-route mr-2 text-slate-400"></i>Mon parcours</h2>
    <p class="text-sm text-slate-600">Vos adhésions, vos mandats et votre participation aux événements, du plus récent au plus ancien.</p>

    <ol class="mt-4 space-y-4 border-s-2 border-slate-100 ps-5">
        @forelse($space['journey'] as $step)
            <li class="relative">
                <span class="absolute -start-[1.9rem] top-0.5 grid h-6 w-6 place-items-center rounded-full text-[11px] {{ $step['ongoing'] ? 'bg-tlink-blue text-white' : 'bg-slate-200 text-slate-600' }}"><i class="{{ $step['icon'] }}"></i></span>
                <p class="text-sm font-semibold text-slate-800">
                    @if($step['url'])<a href="{{ $step['url'] }}" class="hover:underline">{{ $step['title'] }}</a>@else{{ $step['title'] }}@endif
                </p>
                <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span>{{ $fmt($step['from']) }}@if($step['to']) → {{ $fmt($step['to']) }}@elseif($step['ongoing']) → aujourd’hui @endif</span>
                    @if($step['place'])<span>· {{ $step['place'] }}</span>@endif
                    @if($step['status'])<span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">{{ $step['status'] }}</span>@endif
                </p>
            </li>
        @empty
            <li class="text-sm text-slate-500">Votre parcours apparaîtra ici dès votre première adhésion, mandat ou participation.</li>
        @endforelse
    </ol>
</section>
