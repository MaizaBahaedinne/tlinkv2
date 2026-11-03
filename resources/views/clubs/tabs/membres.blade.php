<div class="space-y-5">
    <p class="text-sm text-slate-600">{{ $total }} {{ $total > 1 ? 'membres' : 'membre' }} · classés par département puis par poste.</p>

    @forelse($groups as $group)
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-lg font-semibold text-slate-900">
                {{ $group['department']?->title ?? 'Direction du club' }}
                <span class="text-sm font-normal text-slate-500">({{ count($group['people']) }})</span>
            </h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($group['people'] as $person)
                    <li style="margin-left: {{ min($person['level'], 3) * 0.75 }}rem">
                        @include('clubs.partials.person', ['person' => $person, 'subtitle' => $person['title'], 'badge' => $person['leader'] ? 'Responsable' : null])
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        @if(count($others) === 0)
            <p class="rounded-3xl border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500 shadow-soft">Aucun membre n’est encore rattaché à ce club.</p>
        @endif
    @endforelse

    @if(count($others))
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-lg font-semibold text-slate-900">Adhérents <span class="text-sm font-normal text-slate-500">({{ count($others) }})</span></h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($others as $person)
                    <li>@include('clubs.partials.person', ['person' => $person])</li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
