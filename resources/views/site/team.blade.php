@extends('site.layouts.site')

@section('title', __('site.team.title'))

@section('content')
    @include('site.partials.page-header', ['title' => __('site.team.title'), 'intro' => __('site.team.intro')])

    <div class="mx-auto mt-10 grid max-w-6xl gap-12 px-4">
        @forelse($groups as $category => $nodes)
            <section>
                <h2 class="font-display text-2xl font-bold text-brand-900">{{ __('site.team.categories.'.$category) }}</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($nodes as $node)
                        @foreach($node->assignments as $assignment)
                            @php($person = $assignment->person)
                            @continue(! $person)
                            @php($photo = $person->photoUrl())
                            <div class="site-card flex items-center gap-4 p-4">
                                @if($photo)
                                    <img src="{{ $photo }}" alt="" loading="lazy" class="h-16 w-16 shrink-0 rounded-full object-cover">
                                @else
                                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-brand-100 font-display text-xl font-bold text-brand-800">{{ mb_strtoupper(mb_substr($person->displayName(), 0, 1)) }}</span>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-display font-bold text-brand-900">{{ $person->displayName() }}</p>
                                    <p class="text-sm text-slate-600">{{ $node->title }}</p>
                                    @if($person->job_title && $person->job_title !== $node->title)
                                        <p class="text-xs text-slate-500">{{ $person->job_title }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-slate-500">{{ __('site.empty') }}</p>
        @endforelse
    </div>
@endsection
