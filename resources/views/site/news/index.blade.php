@extends('site.layouts.site')

@section('title', __('site.news.title'))

@section('content')
    @include('site.partials.page-header', ['title' => __('site.news.title'), 'intro' => __('site.news.intro')])

    <section class="mx-auto mt-10 max-w-6xl px-4">
        @if($posts->isNotEmpty())
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach($posts as $post)
                    @include('site.partials.post-card', ['post' => $post])
                @endforeach
            </div>
            <div class="mt-8">{{ $posts->links() }}</div>
        @else
            <p class="text-slate-500">{{ __('site.home.no_news') }}</p>
        @endif
    </section>
@endsection
