@php
    $groups = [
        ['À venir', $space['activities']['upcoming']],
        ['Passées', $space['activities']['past']],
    ];
    $linkable = \App\Support\Challenges\Challenges::enabled();
@endphp

<section id="activites" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-bold text-slate-900">Activités des clubs auxquelles j’ai participé ou je suis inscrit(e)</h2>

    <div class="mt-4 space-y-3">
        @foreach ($groups as [$label, $items])
            <details class="rounded-xl border border-slate-200">
                <summary class="flex cursor-pointer items-center justify-between px-4 py-3 text-sm font-semibold text-slate-800">
                    <span>{{ $label }}</span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ count($items) }}</span>
                </summary>

                <div class="border-t border-slate-100 px-4 py-3">
                    @forelse ($items as $registration)
                        @php $activity = $registration->activity; @endphp
                        <div class="flex items-center gap-3 py-2">
                            <div class="w-12 shrink-0 rounded-lg bg-slate-50 py-1 text-center">
                                <div class="text-lg font-bold leading-none text-slate-900">{{ $activity->starts_at->format('d') }}</div>
                                <div class="text-[11px] uppercase text-slate-500">{{ $activity->starts_at->translatedFormat('M') }}</div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-slate-900">
                                    @if ($linkable)
                                        <a href="{{ route('activities.show', $activity) }}" class="hover:underline">{{ $activity->title }}</a>
                                    @else
                                        {{ $activity->title }}
                                    @endif
                                </div>
                                <div class="truncate text-xs text-slate-500">
                                    {{ $activity->starts_at->translatedFormat('d F Y, H:i') }}
                                    @if ($activity->club) · {{ $activity->club->title }} @endif
                                    · {{ \App\Models\ClubActivity::TYPES[$activity->type] ?? $activity->type }}
                                </div>
                            </div>
                            @if ($registration->checked_in_at)
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">Présent(e)</span>
                            @else
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Inscrit(e)</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Aucune activité {{ $label === 'À venir' ? 'à venir' : 'passée' }}.</p>
                    @endforelse
                </div>
            </details>
        @endforeach
    </div>
</section>
