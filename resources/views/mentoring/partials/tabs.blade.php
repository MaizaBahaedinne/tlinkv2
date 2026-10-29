{{-- Onglets du mentorat selon le rôle du membre (demandeur, mentor, manager mentor, administrateur). --}}
<nav class="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 text-sm" aria-label="Sections du mentorat">
    @foreach($tabs as $tab)
        @php
            $current = request()->routeIs(...$tab['match']);
        @endphp
        <a href="{{ route($tab['route']) }}" @if($current) aria-current="page" @endif
           class="shrink-0 rounded-xl px-4 py-2 font-medium transition {{ $current ? 'bg-rose-50 text-tlink-blue' : 'text-slate-600 hover:bg-slate-50' }}">{{ $tab['label'] }}</a>
    @endforeach
</nav>
