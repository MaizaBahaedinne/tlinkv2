<section class="mx-auto mt-14 max-w-6xl px-4">
    <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.showcase.services_title') }}</h2>
    <div class="mt-6 grid gap-6 md:grid-cols-2">
        @foreach(['store' => 'store.showcase', 'academy' => 'academy.showcase'] as $key => $routeName)
            <a href="{{ route($routeName) }}" class="site-card group flex h-full flex-col p-6 transition hover:-translate-y-1 hover:shadow-lg">
                <span class="w-fit rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-bold text-brand-800">{{ __('site.showcase.service_badge') }}</span>
                <h3 class="mt-3 font-display text-xl font-bold text-brand-900 group-hover:text-brand-600">{{ __('site.showcase.'.$key.'.name') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('site.showcase.'.$key.'.summary') }}</p>
                <span class="mt-auto pt-4 text-sm font-bold text-brand-600">{{ __('site.read_more') }} →</span>
            </a>
        @endforeach
    </div>
</section>
