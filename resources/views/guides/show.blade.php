@extends('layouts.base', ['title' => $guide->title.' | Guides T.Link'])

@section('content')
@php
    $accent = $guide->accent();
    $count = $steps->count();
    $start = ($progress && $progress->completed_at === null) ? min((int) $progress->last_step, max(0, $count - 1)) : 0;
    $tabBase = 'rounded-lg px-3 py-1.5 text-sm font-semibold transition';
    $btn = 'inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40';
@endphp
<div class="min-h-screen bg-tlink-sand pb-24 text-slate-800 lg:pb-12">
    <div class="print:hidden">@include('partials.app-header')</div>

    <main class="app-container space-y-5 py-6" data-guide-root>
        <nav class="print:hidden" aria-label="Fil d’Ariane">
            <a href="{{ route('guides.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Tous les guides</a>
        </nav>

        @unless($guide->isPublished())
            <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 print:hidden" role="status">Brouillon : ce guide n’est visible que de l’administration.</p>
        @endunless

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl text-xl {{ $accent['soft'] }}"><i class="{{ $guide->iconClass() }}" aria-hidden="true"></i></span>
                <div>
                    @if($guide->category)
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $guide->category }}</p>
                    @endif
                    <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $guide->title }}</h1>
                    @if($guide->summary)
                        <p class="mt-1 max-w-3xl text-sm text-slate-600">{{ $guide->summary }}</p>
                    @endif
                    <p class="mt-1 text-xs text-slate-500"><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ $count }} étape{{ $count > 1 ? 's' : '' }} · environ {{ $guide->minutes() }} min</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 print:hidden">
                {{-- Onglets affichés par JavaScript uniquement : sans lui, la notice écrite reste visible. --}}
                <div data-guide-tabs class="hidden rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Mode de lecture">
                    <button type="button" role="tab" data-guide-tab="player" aria-selected="true" class="{{ $tabBase }} bg-white text-slate-900 shadow-sm"><i class="fa-solid fa-play text-xs" aria-hidden="true"></i> Guide animé</button>
                    <button type="button" role="tab" data-guide-tab="text" aria-selected="false" class="{{ $tabBase }} text-slate-500"><i class="fa-solid fa-list-ol text-xs" aria-hidden="true"></i> Notice écrite</button>
                </div>
                <button type="button" data-guide-print class="{{ $btn }}"><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimer</button>
            </div>
        </header>

        {{-- Lecteur animé --}}
        <section class="guide-player hidden overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft print:hidden" data-guide-player
                 data-progress-url="{{ route('guides.progress', $guide->slug) }}" data-start="{{ $start }}" data-bar="{{ $accent['bar'] }}" aria-label="Guide animé : {{ $guide->title }}">
            <div class="flex gap-1.5 px-5 pt-5" data-guide-segments role="group" aria-label="Progression du guide"></div>

            <div data-guide-stage class="p-5 sm:p-8">
                @foreach($steps as $step)
                    @php($notice = $step->callout())
                    <div class="guide-step grid gap-6 lg:grid-cols-5 lg:items-center" data-step data-title="{{ $step->title }}" data-duration="{{ $step->duration }}">
                        <div class="lg:col-span-3">
                            @if($step->image_path)
                                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-md">
                                    <div class="flex items-center gap-1.5 border-b border-slate-200 bg-white px-3 py-2" aria-hidden="true">
                                        <span class="h-2.5 w-2.5 rounded-full bg-rose-300"></span><span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span><span class="h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
                                        <span class="ml-2 h-4 flex-1 rounded-full bg-slate-100"></span>
                                    </div>
                                    <div class="relative">
                                        <img src="{{ $step->imageUrl() }}" alt="Capture de l’étape : {{ $step->title }}" class="block h-auto w-full" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                                        @if($step->hasHotspot())
                                            <span class="guide-pointer" style="--hx: {{ $step->hotspot_x }}%; --hy: {{ $step->hotspot_y }}%" aria-hidden="true">
                                                <span class="guide-pointer__ring"></span>
                                                <i class="fa-solid fa-arrow-pointer guide-pointer__cursor"></i>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="relative grid min-h-56 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 py-10" aria-hidden="true">
                                    <span class="guide-illustration__ring absolute h-36 w-36 rounded-full {{ $accent['soft'] }} opacity-60"></span>
                                    <span class="guide-illustration__icon relative grid h-28 w-28 place-items-center rounded-full text-5xl shadow-lg {{ $accent['strong'] }}"><i class="{{ $step->iconClass() }}"></i></span>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-3 lg:col-span-2">
                            <p class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                                <span class="grid h-6 w-6 place-items-center rounded-full text-xs {{ $accent['strong'] }}">{{ $loop->iteration }}</span> Étape {{ $loop->iteration }} sur {{ $count }}
                            </p>
                            <h2 class="font-display text-xl font-semibold leading-snug text-slate-900">{{ $step->title }}</h2>
                            @if($step->body)
                                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $step->body }}</p>
                            @endif
                            @if($notice)
                                <p class="flex gap-3 rounded-xl border px-3.5 py-3 text-sm {{ $notice['box'] }}">
                                    <i class="fa-solid {{ $notice['icon'] }} mt-0.5" aria-hidden="true"></i>
                                    <span><strong class="font-semibold">{{ $notice['label'] }} :</strong> {{ $notice['text'] }}</span>
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div data-guide-finished hidden class="px-6 py-12 text-center">
                <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-50 text-4xl text-emerald-500"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                <h2 class="mt-4 font-display text-2xl font-semibold text-slate-900">Guide terminé !</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-600">Vous connaissez maintenant l’essentiel. Vous pouvez revoir le guide à tout moment ou consulter la notice écrite.</p>
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <button type="button" data-guide-restart class="{{ $btn }}"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Revoir le guide</button>
                    <a href="{{ route('guides.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Tous les guides <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
                @if($related->isNotEmpty())
                    <div class="mt-8 border-t border-slate-100 pt-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">À lire ensuite</p>
                        <div class="mt-3 flex flex-wrap justify-center gap-2">
                            @foreach($related as $next)
                                <a href="{{ route('guides.show', $next->slug) }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"><i class="{{ $next->iconClass() }} text-xs" aria-hidden="true"></i> {{ $next->title }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
                <p class="text-sm font-semibold text-slate-600" data-guide-counter role="status" aria-live="polite">Étape 1 sur {{ $count }}</p>
                <div class="flex items-center gap-2">
                    <button type="button" data-guide-prev class="{{ $btn }}" aria-label="Étape précédente"><i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i> Précédent</button>
                    <button type="button" data-guide-toggle class="{{ $btn }}" aria-label="Mettre en pause"><i class="fa-solid fa-pause" aria-hidden="true"></i></button>
                    <button type="button" data-guide-next class="inline-flex items-center justify-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90"><span>Suivant</span> <i class="fa-solid fa-chevron-right text-xs" aria-hidden="true"></i></button>
                </div>
            </div>
            <p class="px-5 pb-3 text-center text-xs text-slate-400">Raccourcis : flèches ← → pour changer d’étape, barre d’espace pour la pause.</p>
        </section>

        {{-- Notice écrite : visible sans JavaScript et à l'impression --}}
        <section data-guide-text class="space-y-4 print:block" aria-label="Notice écrite : {{ $guide->title }}">
            <h2 class="font-display text-lg font-semibold text-slate-900">Notice écrite</h2>
            <ol class="space-y-4">
                @foreach($steps as $step)
                    @php($notice = $step->callout())
                    <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm print:break-inside-avoid print:shadow-none">
                        <div class="flex items-start gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-sm font-bold {{ $accent['strong'] }}">{{ $loop->iteration }}</span>
                            <div class="min-w-0 space-y-2">
                                <h3 class="font-display text-base font-semibold text-slate-900">{{ $step->title }}</h3>
                                @if($step->body)
                                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $step->body }}</p>
                                @endif
                                @if($step->image_path)
                                    <img src="{{ $step->imageUrl() }}" alt="Capture de l’étape : {{ $step->title }}" class="max-h-72 w-auto max-w-full rounded-xl border border-slate-200" loading="lazy">
                                @endif
                                @if($notice)
                                    <p class="flex gap-3 rounded-xl border px-3.5 py-3 text-sm {{ $notice['box'] }}">
                                        <i class="fa-solid {{ $notice['icon'] }} mt-0.5" aria-hidden="true"></i>
                                        <span><strong class="font-semibold">{{ $notice['label'] }} :</strong> {{ $notice['text'] }}</span>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </main>
</div>
@vite(['resources/js/guides.js'])
@endsection
