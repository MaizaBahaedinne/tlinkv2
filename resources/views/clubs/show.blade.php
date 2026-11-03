@extends('layouts.base', ['title' => $club->title.' | T.Link'])

@php
    $activeClass = 'bg-tlink-blue text-white shadow-sm';
    $idleClass = 'text-slate-600 hover:bg-rose-50 hover:text-tlink-blue';
    $stats = $hero['stats'];
@endphp

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-5 py-6">
        <p class="text-sm"><a href="{{ route('club.index') }}" class="font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i> Tous les clubs</a></p>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
            <div class="h-40 bg-gradient-to-br from-tlink-blue via-sky-700 to-rose-500 sm:h-56">
                @if($hero['cover'])
                    <img src="{{ $hero['cover'] }}" alt="" class="h-full w-full object-cover">
                @endif
            </div>

            <div class="px-5 pb-5 sm:px-8">
                <div class="-mt-12 flex flex-wrap items-end gap-4">
                    <x-org-logo :url="$hero['logo']" :name="$club->title" class="!h-24 !w-24 !rounded-2xl !border-4 !border-white shadow-soft sm:!h-28 sm:!w-28" />
                    <div class="min-w-0 flex-1 pt-14 sm:pt-0">
                        <h1 class="font-display text-2xl font-semibold text-slate-900 sm:text-3xl">{{ $club->title }}</h1>
                        @if($club->subtitle)
                            <p class="text-sm text-slate-600">{{ $club->subtitle }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                    @if($hero['founded_year'])
                        <span class="rounded-full bg-sky-50 px-3 py-1 text-sky-800"><i class="fa-regular fa-flag" aria-hidden="true"></i> Fondé en {{ $hero['founded_year'] }}@if($hero['age'] !== null && $hero['age'] > 0) · {{ $hero['age'] }} {{ $hero['age'] > 1 ? 'ans' : 'an' }}@endif</span>
                    @endif
                    @if($hero['location'])
                        <span class="rounded-full bg-rose-50 px-3 py-1 text-rose-800"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $hero['location'] }}</span>
                    @endif
                    @if($hero['scope'])
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $hero['scope'] }}</span>
                    @endif
                    @if($club->charter_signed)
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-800"><i class="fa-solid fa-file-signature" aria-hidden="true"></i> Charte signée</span>
                    @endif
                    @if($club->status !== 'active')
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-800">{{ $hero['status'] }}</span>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-4">
                    <dl class="flex gap-6 text-center">
                        <div><dt class="text-xs text-slate-500">Membres</dt><dd class="font-display text-xl font-semibold text-tlink-blue">{{ $stats['members'] }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Activités</dt><dd class="font-display text-xl font-semibold text-tlink-blue">{{ $stats['activities'] }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Actualités</dt><dd class="font-display text-xl font-semibold text-tlink-blue">{{ $stats['news'] }}</dd></div>
                    </dl>
                    @if($hero['links'])
                        <div class="flex gap-2">
                            @foreach($hero['links'] as [$label, $icon, $url])
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $label }}" aria-label="{{ $label }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 text-tlink-blue transition hover:bg-rose-50"><i class="{{ $icon }}" aria-hidden="true"></i></a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <nav id="club-tabs" data-active="{{ $tab }}" role="tablist" aria-label="Sections du club" class="sticky top-0 z-20 -mx-1 flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white/95 p-1.5 shadow-soft backdrop-blur">
            @foreach(\App\Support\ClubPage::TABS as $key => [$label, $icon])
                <a href="{{ route('club.show', [$club, 'onglet' => $key]) }}"
                   id="club-tab-{{ $key }}"
                   role="tab"
                   data-tab="{{ $key }}"
                   data-url="{{ route('club.tab', [$club, $key]) }}"
                   data-active-class="{{ $activeClass }}"
                   data-idle-class="{{ $idleClass }}"
                   aria-selected="{{ $key === $tab ? 'true' : 'false' }}"
                   aria-controls="club-panel"
                   class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition {{ $key === $tab ? $activeClass : $idleClass }}">
                    <i class="{{ $icon }}" aria-hidden="true"></i> {{ $label }}
                </a>
            @endforeach
        </nav>

        <div id="club-panel" role="tabpanel" aria-live="polite" class="transition-opacity">
            @include('clubs.tabs.'.$tab, $panel)
        </div>
    </main>
</div>

<script>
    (function () {
        var nav = document.getElementById('club-tabs');
        var panel = document.getElementById('club-panel');

        if (!nav || !panel || !window.fetch) {
            return;
        }

        var tabs = Array.prototype.slice.call(nav.querySelectorAll('[data-tab]'));
        var current = nav.dataset.active;
        var cache = {};
        cache[current] = panel.innerHTML;

        function paint(tab) {
            current = tab;
            tabs.forEach(function (link) {
                var on = link.dataset.tab === tab;
                link.setAttribute('aria-selected', on ? 'true' : 'false');
                link.className = link.className
                    .replace(link.dataset.activeClass, '').replace(link.dataset.idleClass, '')
                    .trim() + ' ' + (on ? link.dataset.activeClass : link.dataset.idleClass);
            });
        }

        function show(tab, html, push) {
            panel.innerHTML = html;
            panel.classList.remove('opacity-50');
            panel.removeAttribute('aria-busy');
            paint(tab);

            if (push) {
                var link = nav.querySelector('[data-tab="' + tab + '"]');
                history.pushState({ tab: tab }, '', link.href);
            }
        }

        function load(tab, push) {
            if (tab === current && !push) {
                return;
            }

            if (cache[tab] !== undefined) {
                show(tab, cache[tab], push);
                return;
            }

            var link = nav.querySelector('[data-tab="' + tab + '"]');
            panel.classList.add('opacity-50');
            panel.setAttribute('aria-busy', 'true');

            fetch(link.dataset.url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http ' + response.status);
                    }
                    return response.text();
                })
                .then(function (html) {
                    cache[tab] = html;
                    show(tab, html, push);
                })
                .catch(function () {
                    window.location.href = link.href;
                });
        }

        tabs.forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.button === 1) {
                    return;
                }
                event.preventDefault();
                if (link.dataset.tab !== current) {
                    load(link.dataset.tab, true);
                }
            });
        });

        window.addEventListener('popstate', function () {
            var tab = new URLSearchParams(window.location.search).get('onglet') || 'apropos';
            if (nav.querySelector('[data-tab="' + tab + '"]')) {
                load(tab, false);
            }
        });
    })();
</script>
@endsection
