<div class="space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
        <h2 class="font-display text-lg font-semibold text-slate-900">Bureau actuel
            @if($currentMandate)<span class="text-sm font-normal text-slate-500">· mandat {{ $currentMandate->label }}</span>@endif
        </h2>
        @if(count($current))
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($current as $person)
                    <li>@include('clubs.partials.person', ['person' => $person, 'subtitle' => $person['title'], 'badge' => $person['president'] ? 'Président' : null])</li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Le bureau actuel n’est pas encore renseigné.</p>
        @endif
    </section>

    @if(count($presidents))
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-lg font-semibold text-slate-900">Anciens présidents</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($presidents as $item)
                    <li>@include('clubs.partials.person', ['person' => $item['person'], 'subtitle' => 'Mandat '.$item['mandate']->label])</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
        <h2 class="font-display text-lg font-semibold text-slate-900">Histoire du club par mandat</h2>
        <div class="mt-4 space-y-3">
            @forelse($history as $period)
                <details class="rounded-2xl border border-slate-200" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm font-semibold text-slate-800">
                        <span>Mandat {{ $period['mandate']->label }}@if($period['mandate']->bureau_name) <span class="font-normal text-slate-500">· {{ $period['mandate']->bureau_name }}</span>@endif</span>
                        <span class="text-xs font-normal text-slate-500">
                            @if($period['current'])<span class="mr-2 rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-800">En cours</span>@endif
                            @if($period['president']) Président : {{ $period['president']['name'] }}@endif
                        </span>
                    </summary>
                    <ul class="grid gap-3 border-t border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($period['entries'] as $person)
                            <li>@include('clubs.partials.person', ['person' => $person, 'subtitle' => $person['title'], 'badge' => $person['declared'] ? 'Déclaré par le membre' : null])</li>
                        @endforeach
                    </ul>
                </details>
            @empty
                <p class="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">L’historique des bureaux de ce club n’est pas encore renseigné. Les membres peuvent déclarer leur participation à un mandat depuis « Mes adhésions ».</p>
            @endforelse
        </div>
    </section>
</div>
