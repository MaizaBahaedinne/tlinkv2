@extends('layouts.base', ['title' => $name.' | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-6 py-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $name }}</h1>
                    <p class="mt-1 text-sm text-slate-600">Apprenez à votre rythme : chaque leçon vous rapporte des points, chaque module et chaque cours des badges.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-center">
                        <p class="text-2xl font-semibold text-slate-900">{{ $points }}</p>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Points Academy</p>
                    </div>
                    <a href="{{ route('academy.calendar') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-calendar-days mr-1" aria-hidden="true"></i> Calendrier</a>
                    <a href="{{ route('badges.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-medal mr-1" aria-hidden="true"></i> Mes badges</a>
                </div>
            </div>
            @if($subscription)
                <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Abonnement « {{ $subscription->plan?->name }} » actif jusqu’au {{ $subscription->ends_at->translatedFormat('d F Y') }} : tous les cours inclus sont débloqués.</p>
            @endif
        </section>

        <section>
            <h2 class="mb-3 font-display text-lg font-semibold text-slate-900">Catalogue</h2>
            @if($courses->isEmpty())
                <p class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Les premiers cours arrivent bientôt.</p>
            @else
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($courses as $course)
                        @php $mine = $progress[$course->id] ?? null; @endphp
                        <a href="{{ route('academy.courses.show', $course->slug) }}" class="group flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft transition hover:border-tlink-blue">
                            @if($course->coverUrl())
                                <img src="{{ $course->coverUrl() }}" alt="" loading="lazy" class="h-36 w-full object-cover">
                            @else
                                <div class="grid h-36 place-items-center bg-gradient-to-br from-slate-100 to-slate-200 text-3xl text-slate-400"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></div>
                            @endif
                            <div class="flex flex-1 flex-col gap-2 p-5">
                                <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-wide">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">{{ $course->levelLabel() }}</span>
                                    <span class="rounded-full px-2 py-0.5 {{ $course->isFree() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $course->priceLabel() }}</span>
                                </div>
                                <h3 class="font-display text-base font-semibold text-slate-900 group-hover:text-tlink-blue">{{ $course->title }}</h3>
                                @if($course->summary)<p class="text-sm text-slate-600">{{ $course->summary }}</p>@endif
                                <p class="mt-auto text-xs text-slate-500">{{ $course->modules_count }} module(s) · {{ $course->lessons_count }} leçon(s)</p>
                                @if($mine)
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $mine['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="h-full rounded-full bg-tlink-blue" style="width: {{ $mine['percent'] }}%"></div>
                                        </div>
                                        <span class="text-xs font-semibold text-slate-600">{{ $mine['percent'] }}%</span>
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        @if($plans->isNotEmpty())
            <section>
                <h2 class="mb-3 font-display text-lg font-semibold text-slate-900">Abonnements</h2>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($plans as $plan)
                        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                            <h3 class="font-display text-base font-semibold text-slate-900">{{ $plan->name }}</h3>
                            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $plan->priceLabel() }}</p>
                            <p class="text-xs text-slate-500">{{ $plan->durationLabel() }}</p>
                            @if($plan->description)<p class="mt-2 text-sm text-slate-600">{{ $plan->description }}</p>@endif
                            @if($salesEnabled)
                                <form method="post" action="{{ route('academy.plans.subscribe', $plan) }}" class="mt-4">
                                    @csrf
                                    <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Souscrire</button>
                                </form>
                            @else
                                <p class="mt-4 text-xs text-slate-500">Souscription bientôt ouverte.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</div>
@endsection
