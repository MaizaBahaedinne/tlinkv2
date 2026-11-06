@php
    $node = $office['node'];
    $tree = \App\Support\MyOffice::tree($office);
@endphp
<article class="card overflow-hidden">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 bg-white p-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mon poste</p>
            <h3 class="font-display text-xl font-semibold text-slate-900">{{ $node->title }}</h3>
            @if($node->subtitle)<p class="text-sm text-slate-600">{{ $node->subtitle }}</p>@endif
        </div>
        <p class="flex flex-wrap gap-2 text-xs font-medium">
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">Mes supérieurs : {{ count($office['superiors']) }}</span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">Mon équipe : {{ count($office['team']) }} poste{{ count($office['team']) > 1 ? 's' : '' }}</span>
        </p>
    </div>

    @if($office['detached'])
        <p class="mx-5 mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">@if($office['attached_to'])Ce poste est rattaché à « {{ $office['attached_to']->title }} » sans lien hiérarchique : il n’a pas de supérieur.@else Ce poste est rattaché sans lien hiérarchique : il n’a pas de supérieur.@endif</p>
    @elseif($office['superiors'] === [])
        <p class="mx-5 mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">Aucun supérieur : ce poste est au sommet de sa branche.</p>
    @endif

    {{-- Organigramme : supérieurs au-dessus, mon poste au centre, mon équipe en dessous --}}
    <div class="org-tree overflow-x-auto p-5" role="tree" aria-label="Organigramme de {{ $node->title }}">
        <ul>
            @include('office.partials.tree-node', ['item' => $tree])
        </ul>
    </div>

    @if($office['team'] === [])
        <p class="px-5 pb-4 text-sm text-slate-500">Aucun poste sous le vôtre.</p>
    @endif

    @if($office['team_truncated'])
        <p class="px-5 pb-4 text-xs text-slate-500">L’équipe est très étendue : seuls les premiers blocs sont affichés.</p>
    @endif
</article>
