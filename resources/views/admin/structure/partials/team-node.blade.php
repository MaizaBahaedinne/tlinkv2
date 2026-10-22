@php
    $kids = $tree[$item->id] ?? collect();
    $holders = $assignments[$item->id] ?? collect();
    $isPosition = $item->kind === 'position';
    [$short, $tone] = \App\Support\AccessLevels::BADGES[$item->access_level] ?? \App\Support\AccessLevels::BADGES['none'];
@endphp
<li class="team-node">
    <div class="team-card {{ $item->kind === 'club_department' ? 'is-dept' : '' }}">
        <div class="team-head">
            <a href="{{ route('admin.postes.edit', $item) }}" class="team-title">{{ $item->title }}</a>
            @if($isPosition && $item->access_level !== 'none')
                <span class="badge badge-{{ $tone }}">{{ $short }}</span>
            @endif
        </div>
        @if($isPosition)
            <div class="team-holders">
                @forelse($holders as $assignment)
                    @php $name = $assignment->person?->displayName() ?? '—'; @endphp
                    <span class="team-holder"><x-admin.avatar :name="$name" :size="24" /> {{ $name }}</span>
                @empty
                    <span class="badge badge-warning">Vacant</span>
                @endforelse
            </div>
        @endif
    </div>
    @if($kids->count())
        <ul class="team-tree">
            @foreach($kids as $child)
                @include('admin.structure.partials.team-node', ['item' => $child, 'tree' => $tree, 'assignments' => $assignments])
            @endforeach
        </ul>
    @endif
</li>
