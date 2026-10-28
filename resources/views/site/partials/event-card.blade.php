<a href="{{ route('site.events.show', ['event' => $event->id]) }}" class="site-card block overflow-hidden hover:border-brand-600">
    @if($coverUrl = $event->coverUrl())
        <img src="{{ $coverUrl }}" alt="" loading="lazy" class="h-36 w-full object-cover">
    @endif
    <div class="flex gap-4 p-4">
    <div class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-800 text-white">
        <span class="text-xl font-bold leading-none">{{ $event->starts_at->format('d') }}</span>
        <span class="mt-1 text-xs uppercase">{{ $event->starts_at->translatedFormat('M') }}</span>
    </div>
    <div class="min-w-0">
        <h3 class="font-display font-bold text-brand-900">{{ $event->title }}</h3>
        @if(\App\Support\OrgScopes::valid($event->scope))
            <span class="mt-1 inline-block rounded bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-800">{{ __('site.scopes.'.$event->scope.'.name') }}</span>
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
