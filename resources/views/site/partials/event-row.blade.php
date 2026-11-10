@php($past = $past ?? false)
<a href="{{ route('site.events.show', ['event' => $event->id]) }}" class="group flex items-center gap-4 rounded-2xl p-3 transition hover:bg-white/10">
    <div class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-2xl {{ $past ? 'bg-white/10 text-slate-300' : 'bg-brand-600 text-white' }}">
        <span class="font-display text-2xl font-extrabold leading-none">{{ $event->starts_at->format('d') }}</span>
        <span class="mt-1 text-[11px] font-bold uppercase tracking-wide">{{ $event->starts_at->translatedFormat('M Y') }}</span>
    </div>
    <div class="min-w-0">
        <h4 class="font-display text-base font-bold leading-snug text-white group-hover:text-white">{{ $event->title }}</h4>
        @if($place = collect([$event->location, $event->governorate?->name])->filter()->implode(' · '))
            <p class="mt-0.5 truncate text-sm text-slate-400">{{ $place }}</p>
        @endif
    </div>
</a>
