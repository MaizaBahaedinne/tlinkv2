@extends('site.layouts.site')

@section('title', __('site.clubs.title'))

@section('content')
    @include('site.partials.page-header', ['title' => __('site.clubs.title'), 'intro' => __('site.clubs.intro')])

    <section class="mx-auto mt-8 max-w-6xl px-4">
        <form method="get" action="{{ route('site.clubs') }}" class="site-card grid gap-3 p-4 md:grid-cols-5">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('site.clubs.search') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2" aria-label="{{ __('site.clubs.search') }}">
            <select name="scope" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" aria-label="{{ __('site.clubs.all_types') }}">
                <option value="">{{ __('site.clubs.all_types') }}</option>
                @foreach($scopes as $slug)
                    <option value="{{ $slug }}" @selected(($filters['scope'] ?? null) === $slug)>{{ __('site.scopes.'.$slug.'.name') }}</option>
                @endforeach
            </select>
            <select name="gov" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" aria-label="{{ __('site.clubs.all_governorates') }}">
                <option value="">{{ __('site.clubs.all_governorates') }}</option>
                @foreach($governorates as $governorate)
                    <option value="{{ $governorate->id }}" @selected((int) ($filters['gov'] ?? 0) === $governorate->id)>{{ $governorate->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-brand-800 px-4 py-2 text-sm font-bold text-white hover:bg-brand-900">{{ __('site.clubs.filter') }}</button>
                @if(array_filter($filters))
                    <a href="{{ route('site.clubs') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">{{ __('site.clubs.reset') }}</a>
                @endif
            </div>
        </form>

        <p class="mt-4 text-sm font-semibold text-slate-600">{{ trans_choice('site.clubs.count', $clubs->total()) }}</p>

        <div class="mt-4 grid items-start gap-6 lg:grid-cols-2">
            {{-- Liste à gauche --}}
            <div>
                @if($clubs->isNotEmpty())
                    <div class="grid gap-3">
                        @foreach($clubs as $club)
                            <article class="site-card flex items-start justify-between gap-3 p-4 {{ (int) ($filters['gov'] ?? 0) === (int) $club->governorate_id ? 'ring-2 ring-brand-600' : '' }}">
                                <div class="min-w-0">
                                    <span class="w-fit rounded bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-800">
                                        {{ \Illuminate\Support\Facades\Lang::has('site.clubs.categories.'.$club->category) ? __('site.clubs.categories.'.$club->category) : __('site.nav.clubs') }}
                                    </span>
                                    <h3 class="mt-2 font-display text-lg font-bold text-brand-900">{{ $club->title }}</h3>
                                    @if($club->institution_name)
                                        <p class="text-sm text-slate-600">{{ $club->institution_name }}</p>
                                    @endif
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $club->governorate?->name }}
                                        @if($club->founded_on) · {{ __('site.clubs.founded', ['year' => $club->founded_on->year]) }} @endif
                                    </p>
                                </div>
                                @php($facebook = preg_match('#^https?://#i', (string) $club->facebook_url) === 1 ? $club->facebook_url : null)
                                @if($facebook)
                                    <a href="{{ $facebook }}" target="_blank" rel="noopener noreferrer" class="shrink-0 text-sm font-bold text-brand-600 hover:text-brand-800">{{ __('site.clubs.page') }} →</a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-6">{{ $clubs->links() }}</div>
                @endif
            </div>

            {{-- Carte de la Tunisie à droite --}}
            <aside class="lg:sticky lg:top-24" aria-label="{{ __('site.clubs.map') }}">
                <div id="clubs-map" class="clubs-map site-card overflow-hidden"
                     data-markers="{{ json_encode($markers, JSON_UNESCAPED_UNICODE) }}"
                     data-clubs-label="{{ __('site.clubs.map_clubs') }}"
                     data-view-label="{{ __('site.clubs.map_view') }}"></div>
                <p class="mt-2 text-xs text-slate-500">{{ __('site.clubs.map_hint') }}</p>
            </aside>
        </div>
    </section>
@endsection

@push('head')
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/site-map.js'])
    @endif
@endpush
