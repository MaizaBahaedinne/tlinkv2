{{-- Activités de clubs auxquelles j'ai participé --}}
@php
    $activityLinks = \App\Support\Challenges\Challenges::enabled();
@endphp
<section id="activites" class="card p-5">
    <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-flag-checkered mr-2 text-slate-400" aria-hidden="true"></i>Activités auxquelles j’ai participé</h2>

    <ul class="mt-2 divide-y divide-slate-100">
        @forelse($space['activities'] as $registration)
            @php
                $activity = $registration->activity;
            @endphp
            <li class="flex items-center gap-3 py-3">
                <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <span class="text-sm font-bold leading-none">{{ $activity->starts_at->format('d') }}</span>
                    <span class="mt-0.5 text-[10px] uppercase">{{ $activity->starts_at->translatedFormat('M') }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    @if($activityLinks)
                        <a href="{{ route('activities.show', $activity) }}" class="text-sm font-semibold text-slate-800 hover:underline">{{ $activity->title }}</a>
                    @else
                        <p class="text-sm font-semibold text-slate-800">{{ $activity->title }}</p>
                    @endif
                    <p class="text-xs text-slate-500">
                        {{ $activity->starts_at->translatedFormat('d F Y') }}
                        @if($activity->club) · {{ $activity->club->title }}@endif
                        @if($type = \App\Models\ClubActivity::TYPES[$activity->type] ?? null) · {{ $type }}@endif
                    </p>
                    <span class="mt-1 inline-block rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Présent(e)</span>
                </div>
            </li>
        @empty
            <li class="py-2 text-sm text-slate-500">Aucune activité pour le moment. Elles apparaissent ici une fois votre présence enregistrée.</li>
        @endforelse
    </ul>
</section>
