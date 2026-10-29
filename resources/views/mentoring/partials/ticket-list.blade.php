{{-- Liste de tickets : $tickets (paginés), $showRequester, $showMentor, $empty (message si aucun ticket). --}}
<div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
    <ul class="divide-y divide-slate-100">
        @forelse($tickets as $item)
            <li>
                <a href="{{ route('mentoring.show', $item) }}" class="block px-4 py-4 transition hover:bg-slate-50 sm:px-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-slate-500">{{ $item->reference }}</span>
                        @include('mentoring.partials.badge', ['label' => $item->statusLabel(), 'tone' => $item->statusTone()])
                        @include('mentoring.partials.badge', ['label' => \App\Models\MentoringTicket::TYPES[$item->type] ?? $item->type, 'tone' => 'neutral'])
                        @if($item->category !== null && ($showPriority ?? false))
                            @include('mentoring.partials.badge', ['label' => 'Priorité '.mb_strtolower(\App\Models\MentoringTicket::PRIORITIES[$item->priority] ?? $item->priority), 'tone' => ['low' => 'neutral', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger'][$item->priority] ?? 'neutral'])
                        @endif
                    </div>
                    <p class="mt-1.5 font-semibold text-slate-900">{{ $item->title }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $item->created_at->format('d/m/Y') }}
                        @if($item->collectiveLabel() !== null) · {{ $item->collectiveLabel() }} @else · Personnel @endif
                        @if(($showRequester ?? false) && $item->requester) · par {{ $item->requester->name }} @endif
                        @if(($showMentor ?? false)) · {{ $item->mentor ? 'Mentor : '.$item->mentor->name : 'Sans mentor' }} @endif
                        @if($item->category !== null) · {{ \App\Models\MentoringTicket::CATEGORIES[$item->category] ?? $item->category }} @endif
                    </p>
                </a>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-slate-500">{{ $empty ?? 'Aucun ticket.' }}</li>
        @endforelse
    </ul>
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
