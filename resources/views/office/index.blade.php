@extends('layouts.base', ['title' => 'Mon bureau | T.Link'])

@section('content')
@php
    $fmt = fn ($date) => $date ? $date->translatedFormat('d M Y') : null;
    $siteLocale = array_key_exists(app()->getLocale(), config('site.locales')) ? app()->getLocale() : config('site.default_locale');
    $eventUrl = fn ($event) => in_array($event->status, ['published', 'completed'], true) ? route('site.events.show', ['locale' => $siteLocale, 'event' => $event->id]) : null;

    $mandate = \App\Support\Onboarding::currentMandate();
    $words = preg_split('/\s+/u', trim((string) $user->name)) ?: [];
    $initials = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));

    $tiles = [
        ['en-cours', 'Adhésions', count($space['memberships']), 'fa-solid fa-id-card'],
        ['postes', 'Postes', count($offices), 'fa-solid fa-sitemap'],
        ['clubs', 'Clubs', count($space['clubs']), 'fa-solid fa-people-group'],
        ['evenements', 'Événements à venir', $space['upcoming']->count(), 'fa-solid fa-calendar-days'],
    ];
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-6 py-6">
        {{-- En-tête : qui je suis, et l'essentiel en un coup d'œil --}}
        <header class="card flex flex-wrap items-center justify-between gap-4 p-5">
            <div class="flex items-center gap-4">
                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-tlink-blue text-lg font-semibold text-white" aria-hidden="true">{{ $initials }}</span>
                <div>
                    <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mon bureau</h1>
                    <p class="text-sm text-slate-600">{{ $user->name }}@if($mandate) · mandat {{ $mandate->label }}@endif</p>
                </div>
            </div>
            <a href="{{ route('memberships.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-id-card mr-2 text-slate-400" aria-hidden="true"></i>Mes adhésions</a>
        </header>

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                @foreach($errors->all() as $message){{ $message }} @endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach($tiles as [$anchor, $label, $count, $icon])
                @if($count > 0)
                    <a href="#{{ $anchor }}" class="card flex items-center gap-3 p-4 transition hover:border-tlink-blue">
                @else
                    <div class="card flex items-center gap-3 p-4 opacity-60">
                @endif
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-tlink-blue"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                        <span>
                            <span class="block text-2xl font-semibold leading-none text-slate-900">{{ $count }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ $label }}</span>
                        </span>
                @if($count > 0)
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Mon engagement : adhésions et mandats en cours --}}
        @include('office.partials.engagement')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section id="postes" class="space-y-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900"><i class="fa-solid fa-sitemap mr-2 text-slate-400" aria-hidden="true"></i>Mes postes et mon équipe</h2>

                    @forelse($offices as $office)
                        @include('office.partials.post', ['office' => $office])
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-5 text-sm text-slate-600">
                            Vous n’occupez aucun poste dans l’organigramme pour le moment.
                            <a href="{{ route('memberships.index') }}" class="font-semibold text-tlink-blue hover:underline">Candidater à un poste</a>
                        </div>
                    @endforelse
                </section>

                @include('office.partials.clubs')
            </div>

            <div class="space-y-6">
                @include('office.partials.events')
                @include('office.partials.journey')
            </div>
        </div>
    </div>
</div>
@endsection
