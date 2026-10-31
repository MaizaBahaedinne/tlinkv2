@extends('site.layouts.site')

@php
    $title = $item->text('title');
    $body = $item->text('body');
    $description = \Illuminate\Support\Str::limit($item->summary(), 160);
    $cover = $item->coverUrl();
    $shareUrl = $item->publicUrl(app()->getLocale());
    $encodedUrl = rawurlencode($shareUrl);
    $encodedText = rawurlencode($title);
    $networks = [
        'Facebook' => ['href' => 'https://www.facebook.com/sharer/sharer.php?u='.$encodedUrl, 'path' => 'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.49 0-1.96.93-1.96 1.88v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z'],
        'X' => ['href' => 'https://twitter.com/intent/tweet?text='.$encodedText.'&url='.$encodedUrl, 'path' => 'M18.9 1.15h3.68l-8.04 9.19L24 22.85h-7.4l-5.8-7.58-6.64 7.58H.47l8.6-9.83L0 1.15h7.59l5.24 6.93 6.07-6.93Zm-1.29 19.5h2.04L6.49 3.24H4.3l13.31 17.41Z'],
        'LinkedIn' => ['href' => 'https://www.linkedin.com/sharing/share-offsite/?url='.$encodedUrl, 'path' => 'M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z'],
        'WhatsApp' => ['href' => 'https://wa.me/?text='.rawurlencode($title.' '.$shareUrl), 'path' => 'M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.39-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35ZM12.05 21.79h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.83 9.83 0 0 1 2.89 6.99c0 5.45-4.44 9.88-9.88 9.88ZM20.46 3.49A11.8 11.8 0 0 0 12.05 0C5.5 0 .16 5.34.16 11.89c0 2.1.55 4.14 1.59 5.95L.06 24l6.3-1.65a11.88 11.88 0 0 0 5.68 1.45h.01c6.55 0 11.89-5.34 11.89-11.89 0-3.18-1.24-6.17-3.48-8.42Z'],
    ];
    $reactionIcons = ['like' => '👍', 'support' => '🤝', 'insightful' => '💡', 'celebrate' => '👏'];
@endphp

@section('title', $title)
@section('description', $description)
@section('og_type', 'article')
@section('og_image', (string) $cover)

@section('content')
    {{-- Hero : la photo de l'article en grand, titre par-dessus --}}
    <header class="relative isolate flex min-h-[320px] items-end overflow-hidden bg-brand-900 text-white md:min-h-[460px]">
        @if($cover)
            <img src="{{ $cover }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover" loading="eager" decoding="async" fetchpriority="high">
        @endif
        <div class="absolute inset-0 -z-10 bg-linear-to-t from-brand-900/95 via-brand-900/55 to-black/10"></div>

        <div class="mx-auto w-full max-w-4xl px-4 pb-8 pt-24 md:pb-12">
            <a href="{{ route('site.news') }}" class="text-sm font-bold text-white/80 hover:text-white">← {{ __('site.news.title') }}</a>
            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded bg-white/20 px-2 py-0.5 text-xs font-bold backdrop-blur">{{ $item->categoryLabel() }}</span>
                <time datetime="{{ $item->published_at->toDateString() }}" class="text-white/85">{{ __('site.news.published_on', ['date' => $item->published_at->translatedFormat('d F Y')]) }}</time>
            </div>
            <h1 class="mt-3 font-display text-3xl font-bold leading-tight md:text-5xl">{{ $title }}</h1>

            {{-- Partage sur les réseaux sociaux --}}
            <div class="mt-6 flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('site.news.share') }}">
                <span class="me-1 text-xs font-bold uppercase tracking-wide text-white/80">{{ __('site.news.share') }}</span>
                @foreach($networks as $name => $network)
                    <a href="{{ $network['href'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/15 px-3.5 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white hover:text-brand-800" aria-label="{{ __('site.news.share_on', ['network' => $name]) }}">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="currentColor" aria-hidden="true"><path d="{{ $network['path'] }}"/></svg>
                        <span class="hidden sm:inline">{{ $name }}</span>
                    </a>
                @endforeach
                <button type="button" data-copy-link="{{ $shareUrl }}" data-copied="{{ __('site.news.link_copied') }}" class="inline-flex items-center gap-2 rounded-full border border-white/40 bg-white/15 px-3.5 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white hover:text-brand-800">
                    <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1.5-1.5"/></svg>
                    <span data-label>{{ __('site.news.copy_link') }}</span>
                </button>
            </div>
        </div>
    </header>

    <article class="mx-auto mt-10 max-w-3xl px-4">
        @if($body !== '')
            <div class="site-prose text-slate-700">
                @foreach(preg_split('/\R{2,}/u', $body) as $paragraph)
                    <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                @endforeach
            </div>
        @endif

        {{-- Vidéos (YouTube, Facebook, Instagram, TikTok…) : intégrations construites par App\Support\VideoEmbed --}}
        @php($videos = $item->videoEmbeds())
        @if($videos !== [])
            <section class="mt-10" aria-labelledby="videos-title">
                <h2 id="videos-title" class="font-display text-xl font-bold text-brand-900">{{ trans_choice('site.news.videos_title', count($videos)) }}</h2>
                <div class="mt-4 space-y-8">
                    @foreach($videos as $video)
                        <figure>
                            @if($video['embed'])
                                <div class="mx-auto overflow-hidden rounded-2xl border border-slate-200 bg-black shadow-sm" style="{{ $video['layout'] === 'wide' ? 'aspect-ratio:16/9;' : 'max-width:360px;height:'.($video['platform'] === 'instagram' ? '640' : '740').'px;' }}">
                                    <iframe src="{{ $video['embed'] }}" title="{{ $video['title'] !== '' ? $video['title'] : $video['label'].' — '.$title }}" class="h-full w-full border-0" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
                                </div>
                            @endif
                            <figcaption class="mt-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-sm text-slate-600">
                                @if($video['title'] !== '')<span class="font-semibold text-slate-800">{{ $video['title'] }}</span>@endif
                                <a href="{{ $video['url'] }}" target="_blank" rel="noopener noreferrer" class="{{ $video['embed'] ? 'font-semibold text-brand-700 hover:underline' : 'inline-flex rounded-full bg-brand-600 px-5 py-2.5 font-bold text-white hover:bg-brand-800' }}">{{ __('site.news.watch_on', ['platform' => $video['label']]) }}</a>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Réactions et commentaires des membres, lus depuis la publication du fil T.Link --}}
        @if($feed)
            <section class="mt-10 border-t border-slate-200 pt-6" aria-labelledby="community-title">
                <h2 id="community-title" class="font-display text-xl font-bold text-brand-900">{{ __('site.news.community_title') }}</h2>

                @if($feed['reactions_total'] > 0)
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach($reactionIcons as $type => $icon)
                            @if(($feed['reactions'][$type] ?? 0) > 0)
                                <li class="rounded-full bg-brand-100 px-3 py-1 text-sm font-semibold text-brand-800" title="{{ __('site.news.reaction.'.$type) }}">{{ $icon }} {{ __('site.news.reaction.'.$type) }} · {{ $feed['reactions'][$type] }}</li>
                            @endif
                        @endforeach
                    </ul>
                @endif

                <h3 class="mt-6 text-sm font-bold text-slate-700">{{ __('site.news.comments_title', ['count' => $feed['comments_count']]) }}</h3>
                @forelse($feed['comments'] as $comment)
                    <div class="mt-3 rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm font-bold text-slate-800">{{ $comment->author?->name ?? __('site.news.member') }}
                            <span class="font-normal text-slate-500">· {{ $comment->created_at?->translatedFormat('d F Y') }}</span>
                        </p>
                        <p class="mt-1 text-sm text-slate-700">{!! nl2br(e($comment->content)) !!}</p>

                        @foreach($comment->replies as $reply)
                            <div class="mt-3 border-s-2 border-brand-100 ps-3">
                                <p class="text-sm font-bold text-slate-800">{{ $reply->author?->name ?? __('site.news.member') }}
                                    <span class="font-normal text-slate-500">· {{ $reply->created_at?->translatedFormat('d F Y') }}</span>
                                </p>
                                <p class="mt-1 text-sm text-slate-700">{!! nl2br(e($reply->content)) !!}</p>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="mt-2 text-sm text-slate-500">{{ __('site.news.no_comments') }}</p>
                @endforelse

                @if($feed['comments_count'] > $feed['comments']->count())
                    <p class="mt-3 text-sm text-slate-500">{{ __('site.news.more_comments') }}</p>
                @endif

                <a href="{{ route('home') }}" class="mt-5 inline-flex rounded-full bg-brand-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-800">{{ __('site.news.join_cta') }}</a>
            </section>
        @endif
    </article>

    @if($related->isNotEmpty())
        <section class="mx-auto mt-14 max-w-6xl px-4 pb-4">
            <h2 class="font-display text-xl font-bold text-brand-900">
                {{ $relatedInCategory ? __('site.news.same_category', ['category' => $item->categoryLabel()]) : __('site.news.more') }}
            </h2>
            <div class="mt-5 grid gap-5 md:grid-cols-3">
                @foreach($related as $other)
                    @include('site.partials.news-card', ['item' => $other])
                @endforeach
            </div>
        </section>
    @endif
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-copy-link]').forEach(function (button) {
        button.addEventListener('click', function () {
            var label = button.querySelector('[data-label]');
            var original = label.textContent;
            var done = function () {
                label.textContent = button.dataset.copied;
                setTimeout(function () { label.textContent = original; }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(button.dataset.copyLink).then(done);
            } else {
                window.prompt('', button.dataset.copyLink);
            }
        });
    });
</script>
@endpush
