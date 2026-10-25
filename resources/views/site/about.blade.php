@extends('site.layouts.site')

@section('title', __('site.about.title'))

@section('content')
    @include('site.partials.page-header', ['title' => __('site.about.title')])

    <section class="mx-auto mt-10 grid max-w-6xl gap-10 px-4 lg:grid-cols-3">
        <div class="site-prose lg:col-span-2">
            @foreach(preg_split('/\R{2,}/u', $site->get('about_body')) as $paragraph)
                <p class="text-slate-700">{!! nl2br(e(trim($paragraph))) !!}</p>
            @endforeach

            <h2 class="mt-8 font-display text-xl font-bold text-brand-900">{{ __('site.about.mission_title') }}</h2>
            <ul class="mt-3 grid gap-2">
                @foreach(trans('site.about.points') as $point)
                    <li class="site-card p-4 text-sm text-slate-700">{{ $point }}</li>
                @endforeach
            </ul>
        </div>

        <aside class="grid content-start gap-4">
            @foreach(['members', 'clubs', 'governorates', 'events'] as $key)
                <div class="site-card flex items-center justify-between p-4">
                    <span class="text-sm font-semibold text-slate-600">{{ __('site.stats.'.$key) }}</span>
                    <span class="font-display text-2xl font-bold text-brand-800">{{ number_format($stats[$key], 0, ',', ' ') }}</span>
                </div>
            @endforeach
        </aside>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.home.scopes_title') }}</h2>
        @include('site.partials.scope-tree')
    </section>

    <section id="equipe" class="mx-auto mt-14 max-w-6xl scroll-mt-24 px-4" aria-labelledby="equipe-title">
        <h2 id="equipe-title" class="font-display text-2xl font-bold text-brand-900">{{ __('site.team.title') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('site.team.intro') }}</p>
        @include('site.partials.team', ['groups' => $groups])
    </section>
@endsection
