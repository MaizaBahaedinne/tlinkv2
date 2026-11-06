@php
    $node = $item['node'];
    $role = $item['role'];
    $level = $item['level'];
    $ring = match ($role) {
        'me' => 'border-tlink-blue ring-2 ring-tlink-blue/20 bg-white',
        'superior' => 'border-slate-300 bg-slate-50',
        default => 'border-slate-200 bg-white',
    };
    $tag = match ($role) {
        'me' => 'Mon poste',
        'superior' => 'Niveau +'.$level.($level === 1 ? ' · direct' : ''),
        default => 'Niveau −'.abs($level),
    };
    $tagClass = match ($role) {
        'me' => 'bg-tlink-blue text-white',
        'superior' => 'bg-slate-200 text-slate-700',
        default => 'bg-rose-50 text-rose-700',
    };
    $searching = $searchNode === $node->id;
@endphp
<li>
    <div class="org-card w-60 rounded-2xl border p-3 text-left shadow-sm {{ $ring }}">
        <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $tagClass }}">{{ $tag }}</span>
        <p class="mt-1.5 text-sm font-semibold leading-snug text-slate-900">{{ $node->title }}</p>

        @if($item['holders'] === [])
            <p class="mt-1 text-xs italic text-slate-400">Poste vacant</p>
        @else
            <ul class="mt-2 space-y-2 text-xs text-slate-700">
                @foreach($item['holders'] as $holder)
                    <li class="flex items-start justify-between gap-2">
                        <span class="min-w-0">@include('office.partials.holder', ['holder' => $holder, 'withMailto' => $role === 'superior'])</span>
                        @if($item['manageable'])
                            <form method="post" action="{{ route('office.unassign', [$node, $holder['person_id']]) }}" onsubmit="return confirm('Retirer ce titulaire du poste ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-rose-200 bg-white px-2 py-1 text-[11px] font-medium text-rose-700 hover:bg-rose-50">Retirer</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if($role === 'team' && $node->kind === 'position')
            @if($item['manageable'])
                <details class="mt-2 border-t border-slate-100 pt-2" @if($searching) open @endif>
                    <summary class="cursor-pointer text-xs font-semibold text-tlink-blue">Affecter un membre</summary>
                    <form method="get" action="{{ route('office.index') }}#postes" class="mt-2 flex gap-1">
                        <input type="hidden" name="node" value="{{ $node->id }}">
                        <input type="search" name="q" value="{{ $searching ? $term : '' }}" minlength="2" maxlength="80" placeholder="Nom ou e-mail" class="w-full min-w-0 rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs" aria-label="Rechercher un membre pour {{ $node->title }}">
                        <button type="submit" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs hover:bg-slate-50">OK</button>
                    </form>

                    @if($searching && $term !== '')
                        <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                            @forelse($results as $found)
                                <li class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs">
                                    <span class="min-w-0 truncate">{{ $found['name'] }}@if($found['email'])<span class="text-slate-500"> · {{ $found['email'] }}</span>@endif</span>
                                    <form method="post" action="{{ route('office.assign', $node) }}">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $found['id'] }}">
                                        <button type="submit" class="rounded-lg bg-tlink-blue px-2 py-1 text-[11px] font-medium text-white hover:bg-tlink-blue-700">Affecter</button>
                                    </form>
                                </li>
                            @empty
                                <li class="px-2 py-1.5 text-xs text-slate-500">Aucun membre trouvé.</li>
                            @endforelse
                        </ul>
                    @endif
                </details>
            @else
                <p class="mt-2 text-[11px] text-slate-400">Droits supérieurs aux vôtres : gestion réservée à l’administration.</p>
            @endif
        @endif
    </div>

    @if($item['children'] !== [])
        <ul>
            @foreach($item['children'] as $child)
                @include('office.partials.tree-node', ['item' => $child])
            @endforeach
        </ul>
    @endif
</li>
