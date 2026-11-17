{{-- Calendrier : mes tâches, formations, événements, activités et réunions du mois (navigation par ?mois=AAAA-MM) --}}
@php
    $kinds = \App\Support\MyCalendar::KINDS;
    $month = $calendar['month'];
    $monthLabel = ucfirst($month->translatedFormat('F Y'));
    $weekdays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    $monthUrl = fn (string $value) => route('office.index', ['mois' => $value]).'#calendrier';
    $maxChips = 3;
@endphp

<section id="calendrier" class="card p-5" data-cal aria-label="Mon calendrier">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-calendar-check mr-2 text-slate-400" aria-hidden="true"></i>Mon calendrier</h2>
            <p class="text-sm text-slate-500">Mes tâches, formations, événements, activités et réunions au même endroit.</p>
        </div>
        <nav class="flex items-center gap-1.5" aria-label="Navigation du calendrier">
            <a href="{{ $monthUrl($calendar['prev']) }}" class="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" aria-label="Mois précédent"><i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i></a>
            <span class="min-w-[9rem] text-center text-sm font-semibold text-slate-900" aria-live="polite">{{ $monthLabel }}</span>
            <a href="{{ $monthUrl($calendar['next']) }}" class="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" aria-label="Mois suivant"><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></a>
            @unless($calendar['is_current'])
                <a href="{{ route('office.index') }}#calendrier" class="ml-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Aujourd’hui</a>
            @endunless
        </nav>
    </header>

    {{-- Légende : chaque type se masque / s'affiche d'un clic (avec JavaScript) --}}
    <ul class="mt-4 flex flex-wrap gap-2" aria-label="Types d’éléments">
        @foreach($kinds as $key => $kind)
            <li>
                <button type="button" data-cal-filter="{{ $key }}" aria-pressed="true" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition aria-[pressed=false]:opacity-40">
                    <span class="h-2.5 w-2.5 rounded-full {{ $kind['dot'] }}" aria-hidden="true"></span>{{ $kind['label'] }}
                    <span class="rounded-full bg-slate-100 px-1.5 text-[11px] text-slate-600">{{ $calendar['counts'][$key] }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    <div class="mt-4 grid gap-5 lg:grid-cols-3">
        {{-- Grille du mois --}}
        <div class="lg:col-span-2">
            <div class="grid grid-cols-7 gap-px overflow-hidden rounded-2xl border border-slate-200 bg-slate-200">
                @foreach($weekdays as $weekday)
                    <div class="bg-slate-50 py-2 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $weekday }}</div>
                @endforeach

                @foreach($calendar['weeks'] as $week)
                    @foreach($week as $cell)
                        @php
                            $date = $cell['date'];
                            $entries = $cell['entries'];
                        @endphp
                        <div data-cal-cell class="min-h-[4.25rem] bg-white p-1 sm:min-h-[6.5rem] sm:p-1.5 {{ $cell['in_month'] ? '' : 'bg-slate-50/70 text-slate-400' }}">
                            <button type="button" data-cal-pick="{{ $date->toDateString() }}" @disabled($entries === []) aria-label="{{ ucfirst($date->translatedFormat('l j F')) }}{{ $entries !== [] ? ' : '.count($entries).' élément'.(count($entries) > 1 ? 's' : '') : '' }}"
                                class="grid h-6 w-6 place-items-center rounded-full text-xs font-semibold {{ $cell['today'] ? 'bg-tlink-blue text-white' : ($cell['in_month'] ? 'text-slate-700' : 'text-slate-400') }} {{ $entries !== [] ? 'hover:ring-2 hover:ring-slate-200' : 'cursor-default' }}">{{ $date->day }}</button>

                            @if($entries !== [])
                                {{-- Petit écran : une pastille par élément --}}
                                <div class="mt-1 flex flex-wrap gap-0.5 sm:hidden" aria-hidden="true">
                                    @foreach(array_slice($entries, 0, 6) as $entry)
                                        <span data-cal-dot data-kind="{{ $entry['kind'] }}" class="h-1.5 w-1.5 rounded-full {{ $kinds[$entry['kind']]['dot'] }}"></span>
                                    @endforeach
                                </div>

                                {{-- Écran large : les premiers éléments en étiquettes --}}
                                <div class="mt-1 hidden space-y-0.5 sm:block">
                                    @foreach($entries as $entry)
                                        @php $kind = $kinds[$entry['kind']]; @endphp
                                        <a data-cal-chip data-kind="{{ $entry['kind'] }}" @if($entry['url']) href="{{ $entry['url'] }}" @endif @if($loop->index >= $maxChips) hidden @endif
                                           title="{{ trim(($entry['time'] ?? '').' '.$entry['title']) }}"
                                           class="flex items-center gap-1 truncate rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-tight {{ $kind['chip'] }} {{ $entry['state'] === 'done' ? 'line-through opacity-60' : '' }} {{ $entry['url'] ? 'hover:brightness-95' : '' }}">
                                            @if($entry['time'])<span class="shrink-0 font-semibold">{{ $entry['time'] }}</span>@endif
                                            <span class="truncate">{{ $entry['title'] }}</span>
                                        </a>
                                    @endforeach
                                    <button type="button" data-cal-more data-cal-pick="{{ $date->toDateString() }}" @if(count($entries) <= $maxChips) hidden @endif class="px-1 text-[11px] font-semibold text-slate-500 hover:text-slate-800">+{{ count($entries) - $maxChips }} autre{{ count($entries) - $maxChips > 1 ? 's' : '' }}</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Prochainement, puis l'agenda du mois affiché --}}
        <div class="space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Prochainement</h3>
                <ul class="mt-2 space-y-2">
                    @forelse($calendar['upcoming'] as $entry)
                        @php $kind = $kinds[$entry['kind']]; @endphp
                        <li data-cal-item data-kind="{{ $entry['kind'] }}" class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/60 p-2.5">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-xs {{ $kind['soft'] }}" title="{{ $kind['label'] }}"><i class="{{ $kind['icon'] }}" aria-hidden="true"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    @if($entry['url'])<a href="{{ $entry['url'] }}" class="hover:underline">{{ $entry['title'] }}</a>@else{{ $entry['title'] }}@endif
                                </p>
                                <p class="truncate text-xs text-slate-500">{{ ucfirst($entry['at']->translatedFormat('D j M')) }}@if($entry['time']) · {{ $entry['time'] }}@endif @if($entry['meta']) · {{ $entry['meta'] }}@endif</p>
                            </div>
                            @if($entry['state'] === 'late')<span class="shrink-0 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">En retard</span>@endif
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-sm text-slate-500">Rien de prévu dans les 30 prochains jours.</li>
                    @endforelse
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-slate-800">Agenda de {{ lcfirst($monthLabel) }}</h3>
                <div data-cal-agenda class="relative mt-2 max-h-80 space-y-3 overflow-y-auto pr-1">
                    @forelse($calendar['agenda'] as $day => $group)
                        <div data-cal-group data-agenda-day="{{ $day }}" class="rounded-xl border border-slate-100 p-2.5 transition">
                            <p class="text-xs font-bold uppercase tracking-wider {{ $group['date']->isToday() ? 'text-tlink-blue' : 'text-slate-500' }}">{{ ucfirst($group['date']->translatedFormat('l j F')) }}@if($group['date']->isToday()) · aujourd’hui @endif</p>
                            <ul class="mt-1.5 space-y-1.5">
                                @foreach($group['entries'] as $entry)
                                    @php $kind = $kinds[$entry['kind']]; @endphp
                                    <li data-cal-item data-kind="{{ $entry['kind'] }}" class="flex items-start gap-2 {{ $entry['state'] === 'done' ? 'opacity-60' : '' }}">
                                        <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md text-[11px] {{ $kind['soft'] }}" title="{{ $kind['label'] }}"><i class="{{ $kind['icon'] }}" aria-hidden="true"></i></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium leading-snug text-slate-900 {{ $entry['state'] === 'done' ? 'line-through' : '' }}">
                                                @if($entry['url'])<a href="{{ $entry['url'] }}" class="hover:underline">{{ $entry['title'] }}</a>@else{{ $entry['title'] }}@endif
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {{ $entry['time'] ?? 'Journée' }}@if($entry['meta']) · {{ $entry['meta'] }}@endif
                                            </p>
                                        </div>
                                        @if($entry['state'] === 'late')
                                            <span class="shrink-0 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700">En retard</span>
                                        @elseif($entry['badge'])
                                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{{ $entry['badge'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-sm text-slate-500">Rien ce mois-ci : aucune tâche, formation, événement, activité ou réunion.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
