@extends('site.layouts.site')

@section('title', __('site.newsletter.unsubscribe_title'))

@section('content')
    @include('site.partials.page-header', ['title' => __('site.newsletter.unsubscribe_title')])

    <section class="mx-auto mt-10 max-w-xl px-4">
        <div class="site-card p-6">
            @if($done)
                <p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ __('site.newsletter.unsubscribed') }}</p>
            @else
                <p class="text-slate-700">{{ __('site.newsletter.unsubscribe_text') }}</p>
                <form method="post" action="{{ route('site.newsletter.unsubscribe.confirm', ['token' => $token]) }}" class="mt-5">
                    @csrf
                    <button type="submit" class="rounded-xl bg-brand-800 px-6 py-3 font-bold text-white hover:bg-brand-900">{{ __('site.newsletter.unsubscribe_button') }}</button>
                </form>
            @endif
        </div>
    </section>
@endsection
