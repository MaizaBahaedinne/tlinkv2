<a href="{{ route('site.events.show', ['event' => $event->id]) }}" class="site-card group block overflow-hidden transition hover:-translate-y-1 hover:shadow-xl">
    @if($coverUrl = $event->coverUrl())
        <div class="overflow-hidden"><img src="{{ $coverUrl }}" alt="" loading="lazy" class="h-44 w-full object-cover transition duration-700 group-hover:scale-105"></div>
    @endif
    <div class="flex gap-4 p-5">
    <div class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-2xl {{ $event->status === 'completed' ? 'bg-ink' : 'bg-brand-600' }} text-white">
        <span class="font-display text-2xl font-extrabold leading-none">{{ $event->starts_at->format('d') }}</span>
        <span class="mt-1 text-[11px] font-bold uppercase tracking-wide">{{ $event->starts_at->translatedFormat('M') }}</span>
    </div>
    <div class="min-w-0">
        <h3 class="font-display text-lg font-extrabold leading-snug text-ink group-hover:text-brand-600">{{ $event->title }}</h3>
        @if(\App\Support\OrgScopes::valid($event->scope))
            <span class="mt-1 inline-block rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-bold text-brand-800">{{ __('site.scopes.'.$event->scope.'.name') }}</span>
        @endif
        @if($place = collect([$event->location, $event->governorate?->name])->filter()->implode(' · '))
            <p class="mt-1 text-sm text-slate-600">{{ $place }}</p>
        @endif
        @if($priceLabel = $event->priceLabel())
            <span class="mt-2 inline-block rounded bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">{{ $priceLabel }}</span>
        @endif
        @if($event->status === 'completed')
            <span class="mt-2 inline-block rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ __('site.events.status_completed') }}</span>
        @endif
    </div>
    </div>
</a>
