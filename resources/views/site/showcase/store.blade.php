@extends('site.layouts.site')

@section('title', $settings['name'])
@section('description', $settings['tagline'])

@section('content')
    @include('site.partials.page-header', ['title' => $settings['name'], 'intro' => $settings['tagline']])

    <section class="mx-auto mt-10 max-w-6xl px-4">
        <div class="flex flex-wrap gap-3">
            @if($open)
                <a href="{{ route('store.index') }}" class="rounded-xl bg-brand-600 px-6 py-3 font-bold text-white hover:bg-brand-800">{{ __('site.showcase.store.cta_open') }}</a>
            @else
                <span class="rounded-xl border border-brand-800 px-6 py-3 font-bold text-brand-800">{{ __('site.showcase.store.soon') }}</span>
            @endif
            @guest
                <a href="{{ $loginUrl }}" class="rounded-xl border border-slate-300 bg-white px-6 py-3 font-bold text-slate-700 hover:bg-slate-100">{{ __('site.showcase.login') }}</a>
                <a href="{{ $registerUrl }}" class="rounded-xl border border-slate-300 bg-white px-6 py-3 font-bold text-slate-700 hover:bg-slate-100">{{ __('site.showcase.register') }}</a>
            @endguest
        </div>
    </section>

    <section class="mx-auto mt-10 max-w-6xl px-4">
        <div class="grid gap-5 md:grid-cols-3">
            @foreach([1, 2, 3] as $i)
                <article class="site-card p-6">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-800 font-display text-sm font-bold text-white">0{{ $i }}</span>
                    <h2 class="mt-4 font-display text-lg font-bold text-brand-800">{{ __('site.showcase.store.point_'.$i.'_title') }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        {{ $i === 3 ? __('site.showcase.store.point_3_text', ['days' => $settings['return_days']]) : __('site.showcase.store.point_'.$i.'_text') }}
                    </p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.showcase.store.preview') }}</h2>
        @if($products->isEmpty())
            <p class="site-card mt-4 p-6 text-slate-600">{{ $open ? __('site.showcase.store.empty') : __('site.showcase.store.closed') }}</p>
        @else
            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                @foreach($products as $product)
                    <a href="{{ route('store.products.show', $product->slug) }}" class="site-card group block overflow-hidden transition hover:-translate-y-1 hover:shadow-lg">
                        @if($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="" class="h-44 w-full object-cover" loading="lazy">
                        @else
                            <div class="flex h-44 items-center justify-center bg-gradient-to-br from-brand-900 to-brand-800 font-display text-4xl font-bold text-white/80" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</div>
                        @endif
                        <div class="space-y-1 p-4">
                            @if($product->category)<p class="text-xs font-semibold uppercase text-slate-500">{{ $product->category->name }}</p>@endif
                            <p class="font-display font-bold text-brand-900 group-hover:text-brand-600">{{ $product->name }}</p>
                            <p class="text-sm font-bold text-brand-800">{{ $product->priceLabel() }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            <p class="mt-6 text-center"><a href="{{ route('store.index') }}" class="inline-block rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-white hover:bg-brand-800">{{ __('site.showcase.store.cta_all') }}</a></p>
        @endif
    </section>

    @if($settings['contact_email'] !== '' || $settings['contact_phone'] !== '')
        <section class="mx-auto mt-12 max-w-6xl px-4">
            <div class="site-card p-6 text-sm text-slate-600">
                <h2 class="font-display text-lg font-bold text-brand-900">{{ __('site.showcase.store.contact') }}</h2>
                <p class="mt-2">
                    @if($settings['contact_email'] !== '')<a href="mailto:{{ $settings['contact_email'] }}" class="font-semibold text-brand-600">{{ $settings['contact_email'] }}</a>@endif
                    @if($settings['contact_email'] !== '' && $settings['contact_phone'] !== '') · @endif
                    @if($settings['contact_phone'] !== '')<span>{{ $settings['contact_phone'] }}</span>@endif
                </p>
            </div>
        </section>
    @endif
@endsection
