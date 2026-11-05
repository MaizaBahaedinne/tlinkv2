@extends('showcase.layout')

@section('showcase')
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-[#6e0019] via-[#a30027] to-[#d00031] text-white">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-32 -right-20 h-96 w-96 rounded-full bg-[#f5a623]/25 blur-3xl"></div>
        </div>
        <div class="mx-auto max-w-6xl px-4 py-16 md:py-24">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider">
                <span class="h-1.5 w-1.5 rounded-full bg-[#f5a623]"></span> Fondation Tunivisions
            </span>
            <h1 class="mt-5 max-w-3xl font-display text-4xl font-semibold leading-tight md:text-5xl">{{ $name }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-white/85">Apprenez à votre rythme : des cours en ligne, des sessions en direct, des points, des badges et des certificats vérifiables.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('academy.index') }}" class="rounded-xl bg-[#f5a623] px-6 py-3 font-bold text-slate-900 hover:brightness-110">Accéder aux cours</a>
                @guest
                    <a href="{{ $registerUrl }}" class="rounded-xl border border-white/40 px-6 py-3 font-bold hover:bg-white/10">Créer mon compte</a>
                @endguest
            </div>
        </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4">
        <div class="grid gap-5 md:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Un parcours à votre rythme</h2>
                <p class="mt-2 text-sm text-slate-600">Cours organisés en modules et en leçons, accessibles à tout moment, avec un suivi de votre progression.</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Points et badges</h2>
                <p class="mt-2 text-sm text-slate-600">Chaque leçon vous rapporte des points, chaque module et chaque cours terminé un badge.</p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                <h2 class="font-display text-lg font-semibold text-slate-900">Certificats vérifiables</h2>
                <p class="mt-2 text-sm text-slate-600">À la fin d’un cours, obtenez un certificat dont l’authenticité se contrôle en ligne grâce à son code.</p>
            </article>
        </div>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4">
        <h2 class="font-display text-2xl font-semibold text-slate-900">Au catalogue</h2>
        @if($courses->isEmpty())
            <p class="mt-4 rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Les premiers cours arrivent bientôt.</p>
        @else
            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($courses as $course)
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
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if($plans->isNotEmpty())
        <section class="mx-auto mt-12 max-w-6xl px-4">
            <h2 class="font-display text-2xl font-semibold text-slate-900">Abonnements</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach($plans as $plan)
                    <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h3 class="font-display text-lg font-semibold text-slate-900">{{ $plan->name }}</h3>
                        <p class="mt-1 text-sm font-semibold text-tlink-blue">{{ $plan->priceLabel() }} · {{ $plan->durationLabel() }}</p>
                        @if($plan->description)<p class="mt-2 text-sm text-slate-600">{{ $plan->description }}</p>@endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
