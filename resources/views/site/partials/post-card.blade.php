@php
    $content = trim((string) $post->content);
    $firstLine = \Illuminate\Support\Str::limit(strip_tags(preg_split('/\R/u', $content)[0] ?? ''), 90);
    $cover = $post->attachments->first(
        fn ($a) => str_starts_with($a->mime_type, 'image/') && \Illuminate\Support\Facades\Storage::disk($a->disk ?: 'public')->exists($a->path)
    );
@endphp
<article class="site-card flex flex-col overflow-hidden">
    @if($cover)
        <a href="{{ route('site.news.show', ['post' => $post->id]) }}" tabindex="-1" aria-hidden="true">
            <img src="{{ route('site.media', ['attachment' => $cover->id]) }}" alt="" loading="lazy" class="h-44 w-full object-cover">
        </a>
    @endif
    <div class="flex flex-1 flex-col p-5">
        <div class="flex flex-wrap items-center gap-2">
            <time datetime="{{ $post->published_at->toDateString() }}" class="text-xs font-semibold text-brand-600">{{ $post->published_at->translatedFormat('d F Y') }}</time>
            @if(\App\Support\OrgScopes::valid($post->scope))
                <span class="rounded bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-800">{{ __('site.scopes.'.$post->scope.'.name') }}</span>
            @endif
        </div>
        <h3 class="mt-1 font-display text-lg font-bold text-brand-900">
            <a href="{{ route('site.news.show', ['post' => $post->id]) }}" class="hover:text-brand-600">{{ $firstLine }}</a>
        </h3>
        <p class="mt-2 flex-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit(strip_tags($content), 160) }}</p>
        <a href="{{ route('site.news.show', ['post' => $post->id]) }}" class="mt-4 text-sm font-bold text-brand-600 hover:text-brand-800">{{ __('site.read_more') }} →</a>
    </div>
</article>
