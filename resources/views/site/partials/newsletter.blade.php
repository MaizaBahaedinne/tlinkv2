<section id="newsletter" class="mx-auto mt-16 max-w-6xl scroll-mt-24 px-4" aria-labelledby="newsletter-title">
    <div class="newsletter-card" data-reveal>
        <div class="relative grid gap-6 lg:grid-cols-2 lg:items-center">
            <div>
                <h2 id="newsletter-title" class="font-display text-2xl font-bold md:text-3xl">{{ __('site.newsletter.title') }}</h2>
                <p class="mt-2 text-white/90">{{ __('site.newsletter.text') }}</p>
            </div>

            <div>
                @if(session('newsletter_sent'))
                    <p role="status" class="rounded-xl bg-white/15 p-4 text-sm font-semibold">{{ __('site.newsletter.sent') }}</p>
                @else
                    <form method="post" action="{{ route('site.newsletter.store') }}" class="grid gap-3 sm:grid-cols-[1fr_auto]" novalidate>
                        @csrf
                        <div class="hidden" aria-hidden="true">
                            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <div>
                            <label for="newsletter-email" class="sr-only">{{ __('site.contact.email') }}</label>
                            <input id="newsletter-email" type="email" name="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email"
                                   placeholder="{{ __('site.newsletter.placeholder') }}"
                                   class="w-full rounded-xl border border-white/30 bg-white px-4 py-3 text-slate-800 placeholder-slate-400">
                        </div>
                        <button type="submit" class="rounded-xl bg-white px-6 py-3 font-bold text-brand-800 transition hover:bg-brand-100">{{ __('site.newsletter.subscribe') }}</button>
                    </form>
                    @error('email')
                        <p data-newsletter-error class="mt-2 text-sm font-semibold text-white" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="mt-3 text-xs text-white/75">{{ __('site.newsletter.privacy') }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
