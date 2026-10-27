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
        'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.$encodedUrl,
        'X' => 'https://twitter.com/intent/tweet?text='.$encodedText.'&url='.$encodedUrl,
        'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url='.$encodedUrl,
        'WhatsApp' => 'https://wa.me/?text='.rawurlencode($title.' '.$shareUrl),
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

        {{-- Partage sur les réseaux sociaux --}}
        <section class="mt-10 border-t border-slate-200 pt-6" aria-labelledby="share-title">
            <h2 id="share-title" class="text-sm font-bold uppercase tracking-wide text-slate-500">{{ __('site.news.share') }}</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($networks as $name => $href)
                    <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-brand-600 hover:text-brand-600" aria-label="{{ __('site.news.share_on', ['network' => $name]) }}">{{ $name }}</a>
                @endforeach
                <button type="button" data-copy-link="{{ $shareUrl }}" data-copied="{{ __('site.news.link_copied') }}" class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-brand-600 hover:text-brand-600">{{ __('site.news.copy_link') }}</button>
            </div>
        </section>

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
            var label = button.textContent;
            var done = function () {
                button.textContent = button.dataset.copied;
                setTimeout(function () { button.textContent = label; }, 2000);
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
