@php
    $objectives = \App\Models\AcademyCourse::lines($course->objectives);
    $prerequisites = \App\Models\AcademyCourse::lines($course->prerequisites);
    $audience = \App\Models\AcademyCourse::lines($course->audience);
    $lessonCount = $course->modules->sum(fn ($module) => $module->lessons->count());
    $hours = intdiv($totalMinutes, 60);
    $minutes = $totalMinutes % 60;
    $duration = $totalMinutes <= 0 ? null : ($hours > 0 ? $hours.' h'.($minutes > 0 ? ' '.str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) : '') : $minutes.' min');
    $initials = collect(preg_split('/\s+/u', trim((string) $course->instructor_name)) ?: [])->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
@extends('layouts.base', ['title' => $course->title.' | '.$name])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-6 py-6">
        <a href="{{ route('academy.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $name }}</a>

        {{-- Hero --}}
        <section class="relative overflow-hidden rounded-3xl bg-slate-900 text-white shadow-soft">
            @if($course->coverUrl())
                <img src="{{ $course->coverUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30">
            @endif
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-900/70 to-slate-900/30" aria-hidden="true"></div>
            <div class="relative grid gap-6 p-6 sm:p-10 lg:grid-cols-3 lg:items-end">
                <div class="space-y-4 lg:col-span-2">
                    <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-wide">
                        <span class="rounded-full bg-white/15 px-2.5 py-1">{{ $course->levelLabel() }}</span>
                        <span class="rounded-full bg-white/15 px-2.5 py-1">{{ $course->language ?: 'Français' }}</span>
                        <span class="rounded-full px-2.5 py-1 {{ $course->isFree() ? 'bg-emerald-500/90' : 'bg-amber-400 text-slate-900' }}">{{ $course->priceLabel() }}</span>
                    </div>
                    <h1 class="font-display text-3xl font-semibold leading-tight sm:text-4xl">{{ $course->title }}</h1>
                    @if($course->summary)<p class="max-w-2xl text-base text-slate-200 sm:text-lg">{{ $course->summary }}</p>@endif

                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-200">
                        @if($reviewSummary['count'] > 0)
                            <a href="#avis" class="inline-flex items-center gap-2 hover:text-white">
                                <strong class="text-amber-300">{{ number_format($reviewSummary['course_avg'], 1, ',', '') }}</strong>
                                @include('academy._stars', ['rating' => $reviewSummary['course_avg']])
                                <span>({{ $reviewSummary['count'] }} avis)</span>
                            </a>
                        @endif
                        <span><i class="fa-solid fa-users mr-1" aria-hidden="true"></i> {{ $learnersCount }} apprenant(s)</span>
                        @if($course->instructor_name)<a href="#formateur" class="hover:text-white"><i class="fa-solid fa-chalkboard-user mr-1" aria-hidden="true"></i> {{ $course->instructor_name }}</a>@endif
                    </div>
                </div>

                <div class="space-y-3 rounded-2xl bg-white/10 p-4 backdrop-blur">
                    @if($enrollment?->completed_at)
                        <a href="{{ route('academy.certificate', $enrollment->certificate_code) }}" class="block rounded-xl bg-emerald-500 px-4 py-2.5 text-center text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-certificate mr-1" aria-hidden="true"></i> Voir mon certificat</a>
                    @endif
                    @if($canAccess)
                        <form method="post" action="{{ route('academy.courses.start', $course->slug) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white hover:opacity-90">{{ $progress['done'] > 0 ? 'Reprendre le cours' : 'Commencer le cours' }}</button>
                        </form>
                    @elseif($salesEnabled)
                        <form method="post" action="{{ route('academy.courses.buy', $course->slug) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white hover:opacity-90">Acheter ce cours — {{ $course->priceLabel() }}</button>
                        </form>
                    @else
                        <p class="text-sm text-slate-200">Les inscriptions payantes sont momentanément fermées.</p>
                    @endif

                    @if(! $canAccess && $plans->isNotEmpty() && $salesEnabled)
                        <p class="text-xs text-slate-300">Aussi inclus dans :
                            @foreach($plans as $plan)
                                <form method="post" action="{{ route('academy.plans.subscribe', $plan) }}" class="inline">@csrf<button type="submit" class="font-semibold text-white underline">{{ $plan->name }} ({{ $plan->priceLabel() }})</button></form>@if(! $loop->last), @endif
                            @endforeach
                        </p>
                    @endif

                    @if($progress['total'] > 0 && $canAccess)
                        <div>
                            <div class="flex justify-between text-xs text-slate-200"><span>{{ $progress['done'] }} / {{ $progress['total'] }} leçon(s)</span><span class="font-semibold">{{ $progress['percent'] }} %</span></div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-white/20" role="progressbar" aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-tlink-blue" style="width: {{ $progress['percent'] }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
            <div class="space-y-6 lg:col-span-2">
                {{-- Description --}}
                @if($course->description)
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-xl font-semibold text-slate-900">À propos de cette formation</h2>
                        <div class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $course->description }}</div>
                    </section>
                @endif

                {{-- Objectifs --}}
                @if($objectives !== [])
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Objectifs de la formation</h2>
                        <ul class="mt-3 grid gap-x-6 gap-y-2 text-sm text-slate-700 sm:grid-cols-2">
                            @foreach($objectives as $objective)
                                <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check mt-0.5 text-emerald-600" aria-hidden="true"></i><span>{{ $objective }}</span></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Programme --}}
                <section class="space-y-3" id="programme">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Programme</h2>
                    @forelse($course->modules as $module)
                        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                            <h3 class="font-display text-base font-semibold text-slate-900">{{ $loop->iteration }}. {{ $module->title }}</h3>
                            @if($module->description)<p class="mt-1 text-sm text-slate-600">{{ $module->description }}</p>@endif
                            <ul class="mt-3 divide-y divide-slate-100">
                                @foreach($module->lessons as $lesson)
                                    @php $done = in_array($lesson->id, $progress['completed_ids'], true); @endphp
                                    <li class="flex items-center gap-3 py-2 text-sm">
                                        <i class="fa-solid {{ $done ? 'fa-circle-check text-emerald-600' : ($lesson->kind === 'video' ? 'fa-circle-play text-slate-400' : 'fa-file-lines text-slate-400') }}" aria-hidden="true"></i>
                                        @if($canAccess || $lesson->is_preview)
                                            <a href="{{ route('academy.lessons.show', $lesson) }}" class="flex-1 font-medium text-slate-800 hover:text-tlink-blue">{{ $lesson->title }}</a>
                                        @else
                                            <span class="flex-1 text-slate-500">{{ $lesson->title }} <i class="fa-solid fa-lock ml-1 text-xs" aria-hidden="true"></i></span>
                                        @endif
                                        @if($lesson->is_preview && ! $canAccess)<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Aperçu</span>@endif
                                        <span class="text-xs text-slate-500">{{ $lesson->points }} pt @if($lesson->duration_minutes) · {{ $lesson->duration_minutes }} min @endif</span>
                                    </li>
                                @endforeach
                                @if($quizByModule->has($module->id))
                                    @include('academy._quiz-row', ['quiz' => $quizByModule[$module->id], 'isPassed' => in_array($quizByModule[$module->id]->id, $passedQuizIds, true)])
                                @endif
                            </ul>
                        </article>
                    @empty
                        <p class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Le programme de ce cours arrive bientôt.</p>
                    @endforelse
                    @if($finalQuiz)
                        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                            <h3 class="font-display text-base font-semibold text-slate-900">Évaluation finale</h3>
                            <ul class="mt-2">
                                @include('academy._quiz-row', ['quiz' => $finalQuiz, 'isPassed' => in_array($finalQuiz->id, $passedQuizIds, true)])
                            </ul>
                        </article>
                    @endif
                </section>

                {{-- Prérequis et public --}}
                @if($prerequisites !== [] || $audience !== [])
                    <div class="grid gap-4 sm:grid-cols-2">
                        @if($prerequisites !== [])
                            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                                <h2 class="font-display text-lg font-semibold text-slate-900">Prérequis</h2>
                                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                                    @foreach($prerequisites as $item)<li class="flex items-start gap-2"><i class="fa-solid fa-angle-right mt-1 text-tlink-blue" aria-hidden="true"></i><span>{{ $item }}</span></li>@endforeach
                                </ul>
                            </section>
                        @endif
                        @if($audience !== [])
                            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                                <h2 class="font-display text-lg font-semibold text-slate-900">À qui s’adresse cette formation</h2>
                                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                                    @foreach($audience as $item)<li class="flex items-start gap-2"><i class="fa-solid fa-angle-right mt-1 text-tlink-blue" aria-hidden="true"></i><span>{{ $item }}</span></li>@endforeach
                                </ul>
                            </section>
                        @endif
                    </div>
                @endif

                {{-- Formateur --}}
                @if($course->instructor_name)
                    <section id="formateur" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-xl font-semibold text-slate-900">Votre formateur</h2>
                        <div class="mt-4 flex flex-col gap-4 sm:flex-row">
                            @if($course->instructorPhotoUrl())
                                <img src="{{ $course->instructorPhotoUrl() }}" alt="{{ $course->instructor_name }}" class="h-24 w-24 shrink-0 rounded-full object-cover">
                            @else
                                <span class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full bg-rose-50 font-display text-2xl font-semibold text-tlink-blue" aria-hidden="true">{{ $initials }}</span>
                            @endif
                            <div class="space-y-1">
                                <p class="font-display text-lg font-semibold text-slate-900">{{ $course->instructor_name }}</p>
                                @if($course->instructor_title)<p class="text-sm text-slate-500">{{ $course->instructor_title }}</p>@endif
                                @if($reviewSummary['instructor_count'] > 0)
                                    <p class="flex items-center gap-2 text-sm text-slate-600">
                                        <strong class="text-amber-600">{{ number_format($reviewSummary['instructor_avg'], 1, ',', '') }}</strong>
                                        @include('academy._stars', ['rating' => $reviewSummary['instructor_avg']])
                                        <span>({{ $reviewSummary['instructor_count'] }} avis sur le formateur)</span>
                                    </p>
                                @endif
                                @if($course->instructor_bio)<p class="whitespace-pre-line pt-1 text-sm leading-relaxed text-slate-700">{{ $course->instructor_bio }}</p>@endif
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Avis --}}
                <section id="avis" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Avis des apprenants</h2>

                    @if($reviewSummary['count'] > 0)
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="flex items-center gap-4">
                                <p class="font-display text-5xl font-semibold text-slate-900">{{ number_format($reviewSummary['course_avg'], 1, ',', '') }}</p>
                                <div>
                                    @include('academy._stars', ['rating' => $reviewSummary['course_avg'], 'size' => 'text-lg'])
                                    <p class="text-sm text-slate-500">Formation · {{ $reviewSummary['count'] }} avis</p>
                                    @if($reviewSummary['instructor_count'] > 0)
                                        <p class="mt-1 text-sm text-slate-500">Formateur · {{ number_format($reviewSummary['instructor_avg'], 1, ',', '') }} / 5 ({{ $reviewSummary['instructor_count'] }})</p>
                                    @endif
                                </div>
                            </div>
                            <ul class="space-y-1 text-xs text-slate-600">
                                @foreach($reviewSummary['distribution'] as $stars => $total)
                                    <li class="flex items-center gap-2">
                                        <span class="w-8">{{ $stars }} <i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i></span>
                                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-amber-400" style="width: {{ $reviewSummary['count'] > 0 ? round($total / $reviewSummary['count'] * 100) : 0 }}%"></span></span>
                                        <span class="w-6 text-right">{{ $total }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="text-sm text-slate-500">Aucun avis pour le moment.</p>
                    @endif

                    @if($canReview)
                        <form method="post" action="{{ route('academy.courses.review', $course->slug) }}" class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            @csrf
                            <p class="font-semibold text-slate-900">{{ $myReview ? 'Modifier mon avis' : 'Donner mon avis' }}</p>
                            @foreach(['course' => ['Sur la formation', 'course_rating', 'course_comment', true], 'instructor' => ['Sur le formateur', 'instructor_rating', 'instructor_comment', false]] as $key => [$label, $ratingField, $commentField, $required])
                                <fieldset class="space-y-2">
                                    <legend class="text-sm font-semibold text-slate-700">{{ $label }}@if($required) <span class="text-tlink-blue">*</span>@endif</legend>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach([1, 2, 3, 4, 5] as $n)
                                            <label class="cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:border-amber-300 has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                                                <input type="radio" name="{{ $ratingField }}" value="{{ $n }}" class="sr-only" @checked((int) old($ratingField, $myReview?->{$ratingField}) === $n) @if($required) required @endif>
                                                {{ $n }} <i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i>
                                            </label>
                                        @endforeach
                                    </div>
                                    <textarea name="{{ $commentField }}" rows="2" maxlength="1500" placeholder="Votre commentaire (facultatif)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">{{ old($commentField, $myReview?->{$commentField}) }}</textarea>
                                    @error($ratingField)<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                    @error($commentField)<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                </fieldset>
                            @endforeach
                            <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">{{ $myReview ? 'Mettre à jour mon avis' : 'Publier mon avis' }}</button>
                        </form>
                    @else
                        <p class="text-xs text-slate-500">Les apprenants inscrits à la formation peuvent laisser un avis.</p>
                    @endif

                    @if($reviews->isNotEmpty())
                        <ul class="divide-y divide-slate-100">
                            @foreach($reviews as $review)
                                <li class="space-y-2 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-600" aria-hidden="true">{{ mb_strtoupper(mb_substr(\App\Support\Academy\AcademyReviews::displayName($review->user), 0, 1)) }}</span>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ \App\Support\Academy\AcademyReviews::displayName($review->user) }}</p>
                                            <p class="text-xs text-slate-500">{{ $review->created_at->translatedFormat('d F Y') }}</p>
                                        </div>
                                    </div>
                                    <div class="space-y-1 text-sm text-slate-700">
                                        <p class="flex items-center gap-2"><span class="font-semibold">Formation</span> @include('academy._stars', ['rating' => $review->course_rating])</p>
                                        @if($review->course_comment)<p class="whitespace-pre-line">{{ $review->course_comment }}</p>@endif
                                        @if($review->instructor_rating)
                                            <p class="flex items-center gap-2 pt-1"><span class="font-semibold">Formateur</span> @include('academy._stars', ['rating' => $review->instructor_rating])</p>
                                            @if($review->instructor_comment)<p class="whitespace-pre-line">{{ $review->instructor_comment }}</p>@endif
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            {{-- Colonne d'informations --}}
            <aside class="space-y-4 lg:sticky lg:top-4">
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Informations</h2>
                    <dl class="mt-3 divide-y divide-slate-100 text-sm">
                        <div class="flex justify-between gap-3 py-2"><dt class="text-slate-500">Niveau</dt><dd class="font-semibold text-slate-800">{{ $course->levelLabel() }}</dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="text-slate-500">Langue</dt><dd class="font-semibold text-slate-800">{{ $course->language ?: 'Français' }}</dd></div>
                        @if($duration)<div class="flex justify-between gap-3 py-2"><dt class="text-slate-500">Durée</dt><dd class="font-semibold text-slate-800">{{ $duration }}</dd></div>@endif
                        <div class="flex justify-between gap-3 py-2"><dt class="text-slate-500">Modules</dt><dd class="font-semibold text-slate-800">{{ $course->modules->count() }}</dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="text-slate-500">Leçons</dt><dd class="font-semibold text-slate-800">{{ $lessonCount }}</dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="text-slate-500">Apprenants</dt><dd class="font-semibold text-slate-800">{{ $learnersCount }}</dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="text-slate-500">Tarif</dt><dd class="font-semibold text-slate-800">{{ $course->priceLabel() }}</dd></div>
                        <div class="flex justify-between gap-3 py-2"><dt class="text-slate-500">Certificat</dt><dd class="font-semibold text-slate-800">Oui, à la fin du cours</dd></div>
                        @if($quizByModule->isNotEmpty() || $finalQuiz)<div class="flex justify-between gap-3 py-2"><dt class="text-slate-500">Quiz</dt><dd class="font-semibold text-slate-800">{{ $quizByModule->count() + ($finalQuiz ? 1 : 0) }}</dd></div>@endif
                    </dl>
                </section>

                @if($course->badge)
                    <section class="flex items-center gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                        <x-badge-medal :badge="$course->badge" size="md" :locked="! ($enrollment?->completed_at)" />
                        <p class="text-sm text-slate-700">Badge « {{ $course->badge->name }} » à la fin du cours, plus {{ $course->completion_points }} point(s) bonus.</p>
                    </section>
                @endif

                @if($sessions->isNotEmpty())
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Prochaines sessions en direct</h2>
                        <ul class="mt-2 space-y-1 text-sm text-slate-700">
                            @foreach($sessions as $session)
                                <li><strong>{{ $session->title }}</strong> — {{ $session->starts_at->translatedFormat('l d F à H:i') }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('academy.calendar') }}" class="mt-2 inline-block text-sm font-semibold text-tlink-blue hover:underline">Voir tout le calendrier</a>
                    </section>
                @endif
            </aside>
        </div>
    </main>
</div>
@endsection
