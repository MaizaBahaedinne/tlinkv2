<a href="{{ route('site.events.show', ['event' => $event->id]) }}" class="site-card flex gap-4 p-4 hover:border-brand-600">
    <div class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-800 text-white">
        <span class="text-xl font-bold leading-none">{{ $event->starts_at->format('d') }}</span>
        <span class="mt-1 text-xs uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
    </div>
    <div class="min-w-0">
        <h3 class="font-display font-bold text-brand-900">{{ $event->title }}</h3>
        @if($event->location)
            <p class="mt-1 text-sm text-slate-600">{{ $event->location }}</p>
        @endif
        @if($event->status === 'completed')
            <span class="mt-2 inline-block rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ __('site.events.status_completed') }}</span>
        @endif
    </div>
</a>
