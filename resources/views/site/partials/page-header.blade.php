<section class="bg-gradient-to-br from-brand-900 to-brand-800 text-white">
    <div class="mx-auto max-w-6xl px-4 py-12">
        <h1 class="font-display text-3xl font-bold md:text-4xl">{{ $title }}</h1>
        @isset($intro)
            <p class="mt-3 max-w-2xl text-slate-200">{{ $intro }}</p>
        @endisset
    </div>
</section>
