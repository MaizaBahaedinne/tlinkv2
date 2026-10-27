@php
    $title = $item->text('title');
@endphp
<article class="site-card flex flex-col overflow-hidden">
    @if($cover = $item->coverUrl())
        <a href="{{ route('site.news.show', ['news' => $item->id]) }}" tabindex="-1" aria-hidden="true">
            <img src="{{ $cover }}" alt="" loading="lazy" class="h-44 w-full object-cover">
        </a>
    @endif
    <div class="flex flex-1 flex-col p-5">
        <div class="flex flex-wrap items-center gap-2">
            <time datetime="{{ $item->published_at->toDateString() }}" class="text-xs font-semibold text-brand-600">{{ $item->published_at->translatedFormat('d F Y') }}</time>
            @if(\App\Support\OrgScopes::valid($item->scope))
                <span class="rounded bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-800">{{ __('site.scopes.'.$item->scope.'.name') }}</span>
            @endif
        </div>
        <h3 class="mt-1 font-display text-lg font-bold text-brand-900">
            <a href="{{ route('site.news.show', ['news' => $item->id]) }}" class="hover:text-brand-600">{{ $title }}</a>
        </h3>
        <p class="mt-2 flex-1 text-sm text-slate-600">{{ $item->summary() }}</p>
        <a href="{{ route('site.news.show', ['news' => $item->id]) }}" class="mt-4 text-sm font-bold text-brand-600 hover:text-brand-800">{{ __('site.read_more') }} →</a>
    </div>
</article>
