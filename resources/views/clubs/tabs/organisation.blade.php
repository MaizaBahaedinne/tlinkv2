<div class="space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
        <h2 class="font-display text-lg font-semibold text-slate-900">Place dans la hiérarchie</h2>
        <ol class="mt-4 flex flex-wrap items-center gap-2 text-sm">
            <li class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">Fondation Tunivisions</li>
            @foreach($chain as $ancestor)
                <li class="text-slate-400"><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></li>
                <li>
                    @if($ancestor->kind === 'club')
                        <a href="{{ route('club.show', $ancestor) }}" class="rounded-full bg-sky-50 px-3 py-1 font-semibold text-sky-800 hover:underline">{{ $ancestor->title }}</a>
                    @else
                        <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">{{ $ancestor->title }}</span>
                    @endif
                </li>
            @endforeach
            <li class="text-slate-400"><i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></li>
            <li class="rounded-full bg-tlink-blue px-3 py-1 font-semibold text-white">{{ $club->title }}</li>
        </ol>
        @if($scopeLabel)
            <p class="mt-3 text-xs text-slate-500">Périmètre : {{ $scopeLabel }}</p>
        @endif
    </section>

    @if($subClubs->isNotEmpty())
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-lg font-semibold text-slate-900">Clubs rattachés <span class="text-sm font-normal text-slate-500">({{ $subClubs->count() }})</span></h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($subClubs as $sub)
                    <li><a href="{{ route('club.show', $sub) }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3 transition hover:border-tlink-blue/40 hover:bg-slate-50">
                        <x-org-logo :url="$sub->logoUrl()" :name="$sub->title" size="sm" class="!h-12 !w-12 text-base" />
                        <span class="truncate text-sm font-semibold text-slate-800">{{ $sub->title }}</span>
                    </a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
        <h2 class="font-display text-lg font-semibold text-slate-900">Structure interne</h2>
        @if(count($departments))
            <ul class="mt-4 space-y-2">
                @foreach($departments as $department)
                    <li style="margin-left: {{ min($department['depth'], 6) * 1.25 }}rem" class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 px-4 py-2.5">
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-800"><i class="fa-solid fa-diagram-project text-xs text-tlink-blue" aria-hidden="true"></i> {{ $department['node']->title }}</span>
                        <span class="text-xs text-slate-500">{{ $department['positions'] }} {{ $department['positions'] > 1 ? 'postes' : 'poste' }} · {{ $department['people'] }} {{ $department['people'] > 1 ? 'membres' : 'membre' }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Aucun département n’est encore déclaré pour ce club.</p>
        @endif
    </section>

    @if($siblings->isNotEmpty())
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-lg font-semibold text-slate-900">Clubs du même périmètre</h2>
                <a href="{{ route('club.index', ['scope' => $scope]) }}" class="text-xs font-semibold text-tlink-blue hover:underline">Voir tous</a>
            </div>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($siblings as $sibling)
                    <li><a href="{{ route('club.show', $sibling) }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 p-3 transition hover:border-tlink-blue/40 hover:bg-slate-50">
                        <x-org-logo :url="$sibling->logoUrl()" :name="$sibling->title" size="sm" class="!h-12 !w-12 text-base" />
                        <span class="truncate text-sm font-semibold text-slate-800">{{ $sibling->title }}</span>
                    </a></li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
