@php
    [$statusLabel, $statusClass] = $statuses[$membership->status] ?? [$membership->status, 'bg-slate-100 text-slate-600'];
    $place = $membership->node?->title ?? (\App\Support\OrgScopes::label($membership->scope) ?: 'Fondation');
    $began = ($membership->starts_at ?? $membership->created_at)?->format('d/m/Y');
@endphp
<li class="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-sky-100 bg-sky-50/40 px-4 py-3">
    <div class="flex min-w-0 items-start gap-3">
        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700" aria-hidden="true"><i class="fa-solid {{ ($carried ?? false) ? 'fa-rotate' : 'fa-id-card' }} text-xs"></i></span>
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-700">{{ ($carried ?? false) ? 'Adhésion reconduite' : 'Adhésion' }}</p>
            <p class="text-sm font-semibold text-slate-800">
                {{ $place }}
                <span class="ml-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
                {{ \App\Support\OrgScopes::label($membership->scope) }}
                @if($membership->position) · poste visé : {{ $membership->position->title }}@endif
                @if($carried ?? false)
                    · depuis le {{ $began }}
                @else
                    · demandée le {{ $membership->created_at->format('d/m/Y') }}
                    @if($membership->status === 'approved' && $membership->approved_at) · validée le {{ $membership->approved_at->format('d/m/Y') }}@endif
                @endif
                @if($membership->ends_at) · jusqu’au {{ $membership->ends_at->format('d/m/Y') }}@endif
            </p>
            @if($membership->status === 'rejected' && filled($membership->rejection_reason))
                <p class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-800">Motif : {{ $membership->rejection_reason }}</p>
            @endif
        </div>
    </div>
    @if($membership->status === 'pending')
        <form method="post" action="{{ route('memberships.destroy', $membership) }}" onsubmit="return confirm('Retirer cette demande d\u2019adhésion ?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-xl border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Retirer ma demande</button>
        </form>
    @endif
</li>
