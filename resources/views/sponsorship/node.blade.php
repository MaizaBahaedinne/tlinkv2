@php
    $node = $item['node'];
    $root = $item['role'] === 'me';
    $closed = ! $root && $node->capacity !== 'multiple' && $item['holders'] !== [];
    $holders = collect($item['holders'])->pluck('name');
    $isSelected = ! $root && ($selected['node']->id ?? null) === $node->id;
    $search = mb_strtolower($node->title.' '.$holders->implode(' '));
    $card = $root
        ? 'border-tlink-blue bg-white ring-2 ring-tlink-blue/20'
        : ($isSelected ? 'border-tlink-blue bg-rose-50 ring-2 ring-tlink-blue/40' : ($closed ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white hover:border-tlink-blue hover:shadow-md'));
    $tag = $root ? 'Mon poste' : 'Niveau −'.abs($item['level']);
    $tagClass = $root ? 'bg-tlink-blue text-white' : 'bg-rose-50 text-rose-700';
@endphp
<li>
    @if($root || $closed)
        <div @if(! $root) data-sp-node data-search="{{ $search }}" data-open="0" @endif class="w-60 shrink-0 rounded-2xl border p-3 text-left shadow-sm transition {{ $card }}">
    @else
        <a href="{{ route('sponsorship.index', ['poste' => $node->id]) }}#partage" data-sp-node data-search="{{ $search }}" data-open="1" @if($isSelected) aria-current="true" @endif
           class="block w-60 shrink-0 rounded-2xl border p-3 text-left shadow-sm transition {{ $card }}" title="Partager le poste « {{ $node->title }} »">
    @endif
        <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $tagClass }}">{{ $tag }}</span>
        <span class="mt-1.5 block text-sm font-semibold leading-snug text-slate-900">{{ $node->title }}</span>

        @if($root)
            <span class="mt-1 block truncate text-xs text-slate-500">{{ $holders->first() ?? 'Vous' }}</span>
        @elseif($closed)
            <span class="mt-1 block truncate text-xs text-slate-500"><i class="fa-solid fa-lock mr-1" aria-hidden="true"></i>Pourvu : {{ $holders->first() }}</span>
        @elseif($holders->isNotEmpty())
            <span class="mt-1 block truncate text-xs text-slate-500">{{ $holders->count() }} titulaire(s) : {{ $holders->take(2)->implode(', ') }}</span>
            <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-tlink-blue"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Partager</span>
        @else
            <span class="mt-1 block text-xs italic text-emerald-700">Poste vacant</span>
            <span class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-tlink-blue"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Partager</span>
        @endif
    @if($root || $closed)
        </div>
    @else
        </a>
    @endif

    @if($item['children'] !== [])
        <ul>
            @foreach($item['children'] as $child)
                @include('sponsorship.node', ['item' => $child, 'selected' => $selected])
            @endforeach
        </ul>
    @endif
</li>
