@php
    $kids = $tree[$item->id] ?? collect();
    $holders = $assignments[$item->id] ?? collect();
    $isPosition = $item->kind === 'position';
    $isDept = $item->kind === 'club_department';
    $vacant = $isPosition && $holders->isEmpty();
    $open = $isPosition && ($item->capacity === 'multiple' || $holders->isEmpty());
    [$short, $tone] = \App\Support\AccessLevels::BADGES[$item->access_level] ?? \App\Support\AccessLevels::BADGES['none'];
    $teamPositions = $isDept ? $kids->where('kind', 'position') : collect();
    $teamFilled = $teamPositions->filter(fn ($position) => isset($assignments[$position->id]) && $assignments[$position->id]->isNotEmpty())->count();
@endphp
<li class="hz-node">
    <div class="hz-card {{ $isDept ? 'is-dept' : '' }} {{ $vacant ? 'is-vacant' : '' }}">
        <div class="hz-head">
            <a href="{{ route('admin.postes.edit', $item) }}" class="hz-title">{{ $item->title }}</a>
            @if($isPosition && $item->access_level !== 'none')
                <span class="badge badge-{{ $tone }}">{{ $short }}</span>
            @endif
        </div>

        @if($isDept)
            <div class="hz-meta">{{ $teamPositions->count() > 0 ? $teamFilled.' / '.$teamPositions->count().' poste(s) pourvu(s)' : 'Département' }}</div>
        @endif

        @if($isPosition)
            <div class="hz-holders">
                @forelse($holders as $assignment)
                    @php $name = $assignment->person?->displayName() ?? '—'; @endphp
                    <span class="hz-holder"><x-admin.avatar :name="$name" :size="24" /><span>{{ $name }}</span></span>
                @empty
                    <span><span class="badge badge-warning">Vacant</span></span>
                @endforelse
            </div>
            @if($canInvite && $open && $item->joinUrl())
                <button type="button" class="btn {{ $vacant ? 'btn-primary' : 'btn-secondary' }} btn-sm" data-invite-open
                        data-node="{{ $item->id }}" data-title="{{ $item->title }}" data-url="{{ $item->joinUrl() }}"
                        data-action="{{ route('admin.clubs.invite', [$club, $item]) }}"
                        data-single="{{ $item->capacity === 'multiple' ? 0 : 1 }}">
                    <x-admin.icon name="send" :size="14" /> {{ $vacant ? 'Inviter un candidat' : 'Inviter' }}
                </button>
            @endif
        @endif
    </div>
    @if($kids->count())
        <ul class="hz-kids">
            @foreach($kids as $child)
                @include('admin.structure.partials.club-chart-node', ['item' => $child, 'tree' => $tree, 'assignments' => $assignments, 'canInvite' => $canInvite, 'club' => $club])
            @endforeach
        </ul>
    @endif
</li>
