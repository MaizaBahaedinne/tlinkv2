@extends('site.layouts.site')

@section('title', __('site.news.title'))
@section('flush_footer', '1')

@section('content')
    @include('site.partials.page-header', [
        'title' => __('site.news.title'),
        'intro' => __('site.news.intro'),
        'image' => $site->heroImage('hero_bg_mark_a', asset('hero/hero-mark-a.webp')),
    ])

    <section class="bg-white py-16 md:py-24">
        <div class="mx-auto max-w-6xl px-4">
            @include('site.partials.scope-filter', ['routeName' => 'site.news', 'scope' => $scope])

            @if($news->isNotEmpty())
                <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($news as $item)
                        @include('site.partials.news-card', ['item' => $item])
                    @endforeach
                </div>
                <div class="mt-8">{{ $news->links() }}</div>
            @else
                <p class="mt-8 rounded-3xl bg-brand-50 p-8 text-slate-600">{{ __('site.home.no_news') }}</p>
            @endif
        </div>
    </section>
@endsection
