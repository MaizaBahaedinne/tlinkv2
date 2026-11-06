@php
    $node = $office['node'];
    $currentLevel = null;
@endphp
<article class="card overflow-hidden">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 bg-white p-5">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mon poste</p>
            <h3 class="font-display text-xl font-semibold text-slate-900">{{ $node->title }}</h3>
            @if($node->subtitle)<p class="text-sm text-slate-600">{{ $node->subtitle }}</p>@endif
        </div>
        <p class="flex flex-wrap gap-2 text-xs font-medium">
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ count($office['superiors']) }} supérieur{{ count($office['superiors']) > 1 ? 's' : '' }}</span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ count($office['team']) }} poste{{ count($office['team']) > 1 ? 's' : '' }} sous le mien</span>
        </p>
    </div>

    {{-- Supérieurs --}}
    <div class="p-5">
        <h4 class="mb-3 text-sm font-semibold text-slate-800"><i class="fa-solid fa-arrow-up mr-2 text-slate-400" aria-hidden="true"></i>Mes supérieurs</h4>

        @if($office['detached'])
            <p class="mb-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">@if($office['attached_to'])Ce poste est rattaché à « {{ $office['attached_to']->title }} » sans lien hiérarchique : il n’a pas de supérieur.@else Ce poste est rattaché sans lien hiérarchique : il n’a pas de supérieur.@endif</p>
        @endif

        <ol class="space-y-2">
            @forelse($office['superiors'] as $index => $superior)
                <li class="rounded-xl bg-slate-50 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">
                        {{ $superior['node']->title }}
                        <span class="ml-2 rounded-full bg-white px-2 py-0.5 text-xs font-medium text-slate-600">Niveau +{{ $index + 1 }}</span>
                        @if($index === 0)<span class="ml-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Supérieur direct</span>@endif
                    </p>
                    @if($superior['holders'] === [])
                        <p class="text-xs text-slate-500">Poste vacant.</p>
                    @else
                        <ul class="mt-2 space-y-2 text-sm text-slate-700">
                            @foreach($superior['holders'] as $holder)
                                <li>@include('office.partials.holder', ['holder' => $holder, 'withMailto' => true])</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @empty
                <li class="text-sm text-slate-500">Aucun supérieur : ce poste est au sommet de sa branche.</li>
            @endforelse
        </ol>
    </div>

    {{-- Équipe --}}
    <div class="border-t border-slate-100 p-5">
        <h4 class="mb-3 text-sm font-semibold text-slate-800"><i class="fa-solid fa-arrow-down mr-2 text-slate-400" aria-hidden="true"></i>Mon équipe (postes sous le mien)</h4>

        @forelse($office['team'] as $row)
            @php
                $member = $row['node'];
            @endphp
            @if($currentLevel !== $row['depth'])
                @php
                    $currentLevel = $row['depth'];
                @endphp
                <h5 class="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 first:mt-0">Niveau −{{ $row['depth'] }}@if($row['depth'] === 1) · sous mon poste @endif</h5>
            @endif
            <div class="mb-2 rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-sm font-semibold text-slate-800">{{ $member->title }}</p>

                @if($member->kind === 'position')
                    @if($row['holders'] === [])
                        <p class="mt-1 text-xs text-slate-500">Poste vacant.</p>
                    @else
                        <ul class="mt-2 space-y-1">
                            @foreach($row['holders'] as $holder)
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-700">
                                    <span>
                                        @include('office.partials.holder', ['holder' => $holder])
                                    </span>
                                    @if($row['manageable'])
                                        <form method="post" action="{{ route('office.unassign', [$member, $holder['person_id']]) }}" onsubmit="return confirm('Retirer ce titulaire du poste ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-200 bg-white px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Retirer</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($row['manageable'])
                        <form method="get" action="{{ route('office.index') }}#postes" class="mt-3 flex gap-2">
                            <input type="hidden" name="node" value="{{ $member->id }}">
                            <input type="search" name="q" value="{{ $searchNode === $member->id ? $term : '' }}" minlength="2" maxlength="80" placeholder="Nom ou e-mail du membre à affecter" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm" aria-label="Rechercher un membre pour {{ $member->title }}">
                            <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Chercher</button>
                        </form>

                        @if($searchNode === $member->id && $term !== '')
                            <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                                @forelse($results as $found)
                                    <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                        <span>{{ $found['name'] }}@if($found['email'])<span class="text-slate-500"> · {{ $found['email'] }}</span>@endif</span>
                                        <form method="post" action="{{ route('office.assign', $member) }}">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $found['id'] }}">
                                            <button type="submit" class="rounded-lg bg-tlink-blue px-2.5 py-1 text-xs font-medium text-white hover:bg-tlink-blue-700">Affecter</button>
                                        </form>
                                    </li>
                                @empty
                                    <li class="px-3 py-2 text-sm text-slate-500">Aucun membre trouvé.</li>
                                @endforelse
                            </ul>
                        @endif
                    @else
                        <p class="mt-2 text-xs text-slate-400">Ce poste accorde des droits supérieurs aux vôtres : sa gestion reste réservée à l’administration.</p>
                    @endif
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-500">Aucun poste sous le vôtre.</p>
        @endforelse

        @if($office['team_truncated'])
            <p class="mt-2 text-xs text-slate-500">L’équipe est très étendue : seuls les premiers blocs sont affichés.</p>
        @endif
    </div>
</article>
