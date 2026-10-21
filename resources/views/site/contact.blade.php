@extends('site.layouts.site')

@section('title', __('site.contact.title'))

@section('content')
    @include('site.partials.page-header', ['title' => __('site.contact.title'), 'intro' => __('site.contact.intro')])

    <section class="mx-auto mt-10 grid max-w-6xl gap-8 px-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if(session('contact_sent'))
                <div role="status" class="mb-6 rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ __('site.contact.sent') }}</div>
            @endif

            <form method="post" action="{{ route('site.contact.store') }}" class="site-card grid gap-4 p-6">
                @csrf
                <div class="hidden" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                @foreach(['name' => 'text', 'email' => 'email', 'subject' => 'text'] as $field => $type)
                    <div>
                        <label for="f-{{ $field }}" class="text-sm font-bold text-slate-700">{{ __('site.contact.'.$field) }}</label>
                        <input id="f-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" required maxlength="{{ $field === 'subject' ? 160 : ($field === 'email' ? 190 : 120) }}"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                <div>
                    <label for="f-message" class="text-sm font-bold text-slate-700">{{ __('site.contact.message') }}</label>
                    <textarea id="f-message" name="message" rows="6" required minlength="10" maxlength="3000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="w-fit rounded-xl bg-brand-800 px-6 py-3 font-bold text-white hover:bg-brand-900">{{ __('site.contact.send') }}</button>
            </form>
        </div>

        <aside class="site-card h-fit p-6">
            <h2 class="font-display text-lg font-bold text-brand-900">{{ __('site.contact.details') }}</h2>
            <dl class="mt-4 grid gap-4 text-sm">
                @if($email = $site->get('contact_email'))
                    <div><dt class="font-bold text-slate-500">{{ __('site.contact.email') }}</dt><dd><a href="mailto:{{ $email }}" class="text-brand-600 hover:underline">{{ $email }}</a></dd></div>
                @endif
                @if($phone = $site->get('contact_phone'))
                    <div><dt class="font-bold text-slate-500">{{ __('site.contact.phone') }}</dt><dd dir="ltr" class="text-start">{{ $phone }}</dd></div>
                @endif
                @if($address = $site->get('contact_address'))
                    <div><dt class="font-bold text-slate-500">{{ __('site.contact.address') }}</dt><dd>{{ $address }}</dd></div>
                @endif
            </dl>
        </aside>
    </section>
@endsection
