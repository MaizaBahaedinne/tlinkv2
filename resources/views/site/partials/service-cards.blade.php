<section class="bg-ink py-16 text-white md:py-24">
    <div class="mx-auto max-w-6xl px-4">
        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-brand-600">{{ __('site.showcase.service_badge') }}</p>
        <h2 class="mt-3 font-display text-3xl font-extrabold md:text-5xl">{{ __('site.showcase.services_title') }}</h2>
        <div class="mt-10 grid gap-6 md:grid-cols-2">
            @foreach(['store' => 'store.showcase', 'academy' => 'academy.showcase'] as $key => $routeName)
                <a href="{{ route($routeName) }}" class="group flex h-full flex-col rounded-3xl bg-white/10 p-8 transition hover:-translate-y-1 hover:bg-brand-600">
                    <span class="w-fit rounded-full bg-brand-600 px-3 py-1 text-xs font-bold text-white group-hover:bg-white group-hover:text-brand-600">{{ __('site.showcase.service_badge') }}</span>
                    <h3 class="mt-4 font-display text-2xl font-extrabold">{{ __('site.showcase.'.$key.'.name') }}</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-300 group-hover:text-white">{{ __('site.showcase.'.$key.'.summary') }}</p>
                    <span class="mt-auto pt-6 text-sm font-bold">{{ __('site.read_more') }} →</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
