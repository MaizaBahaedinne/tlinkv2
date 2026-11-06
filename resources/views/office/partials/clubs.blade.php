{{-- Mes clubs et leur hiérarchie --}}
<section id="clubs" class="space-y-4">
    <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-people-group mr-2 text-slate-400" aria-hidden="true"></i>Mes clubs</h2>

    @forelse($space['clubs'] as $club)
        <details class="card overflow-hidden" @if($loop->first) open @endif>
            <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-2 bg-white p-5">
                <span>
                    <span class="font-display text-lg font-semibold text-slate-900">{{ $club['node']->title }}</span>
                    <span class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        @if($club['governorate'])<span><i class="fa-solid fa-location-dot mr-1" aria-hidden="true"></i>{{ $club['governorate'] }}</span>@endif
                        @if($club['membership'])<span class="rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700">{{ $club['membership'] }}</span>@endif
                        @foreach($club['my_roles'] as $role)<span class="rounded-full bg-rose-50 px-2 py-0.5 font-medium text-rose-700">{{ $role }}</span>@endforeach
                    </span>
                </span>
                <i class="fa-solid fa-chevron-down text-xs text-slate-400" aria-hidden="true"></i>
            </summary>

            <div class="border-t border-slate-100 p-5">
                <h3 class="mb-3 text-sm font-semibold text-slate-800"><i class="fa-solid fa-sitemap mr-2 text-slate-400" aria-hidden="true"></i>Hiérarchie du club</h3>
                @forelse($club['bureau'] as $row)
                    @php
                        $rowNode = $row['node'];
                    @endphp
                    <div class="mb-2" style="margin-inline-start: {{ min($row['depth'], 6) * 1.25 }}rem">
                        @if($rowNode->kind === 'position')
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
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
        </details>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-5 text-sm text-slate-600">
            Vous n’êtes rattaché(e) à aucun club pour le moment.
            <a href="{{ route('memberships.index') }}" class="font-semibold text-tlink-blue hover:underline">Rejoindre un club</a>
        </div>
    @endforelse
</section>
