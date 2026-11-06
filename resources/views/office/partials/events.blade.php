{{-- Événements : à venir en évidence, passés repliés --}}
@php
    $eventRow = function ($event) use ($space, $eventUrl) {
        return [
            'event' => $event,
            'url' => $eventUrl($event),
            'registration' => \App\Support\MyOfficeSpace::registrationLabel($space['registrations'][$event->id] ?? null),
        ];
    };
@endphp
<section id="evenements" class="card p-5">
    <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-calendar-days mr-2 text-slate-400" aria-hidden="true"></i>Événements</h2>

    @foreach([['À venir', $space['upcoming'], 'Aucun événement à venir.', true], ['Passés', $space['past'], 'Aucun événement passé.', false]] as [$heading, $events, $empty, $expanded])
        @php
            $list = view()->make('office.partials.event-list', ['rows' => $events->map($eventRow), 'empty' => $empty])->render();
        @endphp
        @if($expanded)
            <h3 class="mt-3 text-sm font-semibold text-slate-800">{{ $heading }}</h3>
            {!! $list !!}
        @else
            <details class="mt-4 border-t border-slate-100 pt-3">
                <summary class="cursor-pointer text-sm font-semibold text-slate-800">{{ $heading }} ({{ $events->count() }})</summary>
                {!! $list !!}
            </details>
        @endif
    @endforeach
</section>
