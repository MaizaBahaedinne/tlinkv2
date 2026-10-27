@extends('site.layouts.site')

@php
    $title = $item->text('title');
    $body = $item->text('body');
    $description = \Illuminate\Support\Str::limit($item->summary(), 160);
@endphp

@section('title', $title)
@section('description', $description)

@section('content')
    <article class="mx-auto mt-10 max-w-3xl px-4">
        <a href="{{ route('site.news') }}" class="text-sm font-bold text-brand-600 hover:text-brand-800">← {{ __('site.news.title') }}</a>

        <h1 class="mt-4 font-display text-3xl font-bold text-brand-900">{{ $title }}</h1>
        <p class="mt-2 text-sm text-slate-500">
            {{ __('site.news.published_on', ['date' => $item->published_at->translatedFormat('d F Y')]) }}
        </p>

        @if($cover = $item->coverUrl())
            <img src="{{ $cover }}" alt="" class="mt-6 w-full rounded-xl object-cover">
        @endif

        @if($body !== '')
            <div class="site-prose mt-6 text-slate-700">
                @foreach(preg_split('/\R{2,}/u', $body) as $paragraph)
                    <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                @endforeach
            </div>
        @endif
    </article>

    @if($related->isNotEmpty())
        <section class="mx-auto mt-14 max-w-6xl px-4">
            <h2 class="font-display text-xl font-bold text-brand-900">{{ __('site.news.more') }}</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-3">
                @foreach($related as $other)
                    @include('site.partials.news-card', ['item' => $other])
                @endforeach
            </div>
        </section>
    @endif
@endsection
