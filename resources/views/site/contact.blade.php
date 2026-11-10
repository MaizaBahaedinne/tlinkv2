@extends('site.layouts.site')

@section('title', __('site.contact.title'))
@section('flush_footer', '1')

@section('content')
    @include('site.partials.page-header', [
        'title' => __('site.contact.title'),
        'intro' => __('site.contact.intro'),
        'kicker' => __('site.contact.kicker'),
        'image' => $site->heroImage('hero_bg_mark_a', asset('hero/hero-mark-a.webp')),
    ])

    <section class="bg-white py-16 md:py-24">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 lg:grid-cols-5">
            <aside class="h-fit rounded-3xl bg-ink p-8 text-white shadow-xl shadow-black/10 lg:col-span-2 md:p-10">
                <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-brand-600">{{ __('site.contact.kicker') }}</p>
                <h2 class="mt-3 font-display text-3xl font-extrabold">{{ __('site.contact.details') }}</h2>
                <dl class="mt-8 grid gap-6 text-sm">
                    @if($email = $site->get('contact_email'))
                        <div class="border-s-4 border-brand-600 ps-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('site.contact.email') }}</dt><dd class="mt-1 text-base font-semibold"><a href="mailto:{{ $email }}" class="hover:text-brand-600">{{ $email }}</a></dd></div>
                    @endif
                    @if($phone = $site->get('contact_phone'))
                        <div class="border-s-4 border-brand-600 ps-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('site.contact.phone') }}</dt><dd dir="ltr" class="mt-1 text-start text-base font-semibold">{{ $phone }}</dd></div>
                    @endif
                    @if($address = $site->get('contact_address'))
                        <div class="border-s-4 border-brand-600 ps-4"><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('site.contact.address') }}</dt><dd class="mt-1 text-base font-semibold">{{ $address }}</dd></div>
                    @endif
                </dl>
            </aside>

            <div class="lg:col-span-3">
                @if(session('contact_sent'))
                    <div role="status" class="mb-6 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ __('site.contact.sent') }}</div>
                @endif

                <form method="post" action="{{ route('site.contact.store') }}" class="site-card grid gap-5 p-6 md:p-10">
                    @csrf
                    <div class="hidden" aria-hidden="true">
                        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    @foreach(['name' => 'text', 'email' => 'email', 'subject' => 'text'] as $field => $type)
                        <div>
                            <label for="f-{{ $field }}" class="text-sm font-bold text-ink">{{ __('site.contact.'.$field) }}</label>
                            <input id="f-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field, $field === 'subject' ? request()->query('subject') : null) }}" required maxlength="{{ $field === 'subject' ? 160 : ($field === 'email' ? 190 : 120) }}"
                                   class="site-input">
                            @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach

                    <div>
                        <label for="f-message" class="text-sm font-bold text-ink">{{ __('site.contact.message') }}</label>
                        <textarea id="f-message" name="message" rows="6" required minlength="10" maxlength="3000" class="site-input">{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="w-fit rounded-full bg-brand-600 px-8 py-3.5 font-bold text-white shadow-lg shadow-brand-600/30 transition hover:bg-ink">{{ __('site.contact.send') }}</button>
                </form>
            </div>
        </div>
    </section>
@endsection
