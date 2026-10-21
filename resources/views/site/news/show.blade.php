@extends('site.layouts.site')

@php
    $content = trim((string) $post->content);
    $firstLine = \Illuminate\Support\Str::limit(strip_tags(preg_split('/\R/u', $content)[0] ?? ''), 90);
    $images = $media->filter(fn ($a) => str_starts_with($a->mime_type, 'image/'));
    $videos = $media->filter(fn ($a) => str_starts_with($a->mime_type, 'video/'));
@endphp

@section('title', $firstLine)
@section('description', \Illuminate\Support\Str::limit(strip_tags($content), 160))

@section('content')
    <article class="mx-auto mt-10 max-w-3xl px-4">
        <a href="{{ route('site.news') }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">← {{ __('site.news.title') }}</a>

        <h1 class="mt-4 font-display text-3xl font-bold text-brand-900">{{ $firstLine }}</h1>
        <p class="mt-2 text-sm text-slate-500">
            {{ __('site.news.published_on', ['date' => $post->published_at->translatedFormat('d F Y')]) }}
            @if($post->author) · {{ __('site.by') }} {{ $post->author->name }} @endif
        </p>

        @if($images->isNotEmpty())
            <div class="mt-6 grid gap-3 {{ $images->count() > 1 ? 'sm:grid-cols-2' : '' }}">
                @foreach($images as $image)
                    <img src="{{ route('site.media', ['attachment' => $image->id]) }}" alt="" loading="lazy" class="w-full rounded-xl object-cover">
                @endforeach
            </div>
        @endif

        @foreach($videos as $video)
            <video controls preload="metadata" class="mt-6 w-full rounded-xl" src="{{ route('site.media', ['attachment' => $video->id]) }}">{{ __('site.news.video') }}</video>
        @endforeach

        <div class="site-prose mt-6 text-slate-700">
            @foreach(preg_split('/\R{2,}/u', $content) as $paragraph)
                <p>{!! nl2br(e(trim($paragraph))) !!}</p>
            @endforeach
        </div>
    </article>

    @if($related->isNotEmpty())
        <section class="mx-auto mt-14 max-w-6xl px-4">
            <h2 class="font-display text-xl font-bold text-brand-900">{{ __('site.news.more') }}</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-3">
                @foreach($related as $item)
                    @include('site.partials.post-card', ['post' => $item])
                @endforeach
            </div>
        </section>
    @endif
@endsection
