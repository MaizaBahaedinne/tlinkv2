@extends('layouts.base', ['title' => 'Ma carte de membre | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container mx-auto max-w-5xl space-y-6 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Ma carte de membre</h1>
            <p class="max-w-2xl text-sm text-slate-600">
                Une carte par adhésion validée, renouvelée à chaque mandat tant que l’adhésion reste active. Présentez le QR code à l’entrée d’une activité, d’un événement ou d’un local : il change toutes les 30 secondes.
            </p>
        </div>

        @php($valid = $cards->filter(fn ($card) => $card->isValid()))
        @php($past = $cards->reject(fn ($card) => $card->isValid()))

        @if($valid->isEmpty())
            <section class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-soft">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-50 text-tlink-blue"><i class="fa-regular fa-id-card text-2xl" aria-hidden="true"></i></span>
                <h2 class="mt-4 font-display text-lg font-semibold text-slate-900">Aucune carte active</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-600">
                    @if($mandate)
                        Votre carte est émise dès que votre adhésion est validée pour le mandat {{ $mandate->label }}.
                    @else
                        Aucun mandat n’est en cours pour le moment : les cartes sont émises à l’ouverture du prochain mandat.
                    @endif
                </p>
                <a href="{{ route('memberships.index') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-people-group text-xs" aria-hidden="true"></i> Mes adhésions</a>
            </section>
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            @foreach($valid as $card)
                @php($entity = $card->membership?->node?->title ?? ($scopeLabels[$card->membership?->scope] ?? 'Fondation Tunivisions'))
                <article class="overflow-hidden rounded-3xl bg-gradient-to-br from-tlink-blue to-[#7a001c] text-white shadow-soft" data-card data-code-url="{{ route('card.code', $card) }}">
                    <div class="flex items-start justify-between gap-3 px-6 pt-6">
                        <img src="{{ asset('logoFondation-white.webp') }}" alt="Fondation Tunivisions" class="h-10 w-auto max-w-[9rem] object-contain">
                        <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider">Mandat {{ $card->mandate?->label }}</span>
                    </div>

                    <div class="px-6 pb-5 pt-5">
                        <p class="text-xs uppercase tracking-widest text-white/70">Membre</p>
                        <p class="font-display text-2xl font-semibold leading-tight">{{ $card->user->name }}</p>
                        <p class="mt-2 inline-block rounded-full bg-white/15 px-3 py-1 text-sm font-semibold" data-card-role>{{ $roles[$card->id] ?? 'Membre' }}</p>
                        <div class="mt-3 flex items-center gap-3">
                            @if(! empty($logos[$card->id]))
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1">
                                    <img src="{{ $logos[$card->id] }}" alt="Logo {{ $entity }}" class="max-h-full max-w-full object-contain">
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="text-sm text-white/90">{{ $entity }}</p>
                                <p class="text-xs text-white/70">{{ $scopeLabels[$card->membership?->scope] ?? '' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="absolute -left-3 top-1/2 h-6 w-6 -translate-y-1/2 rounded-full bg-tlink-sand"></div>
                        <div class="absolute -right-3 top-1/2 h-6 w-6 -translate-y-1/2 rounded-full bg-tlink-sand"></div>
                        <div class="mx-6 border-t-2 border-dashed border-white/30"></div>
                    </div>

                    <div class="flex items-center gap-5 px-6 pb-6 pt-5">
                        <div class="w-36 shrink-0 rounded-2xl bg-white p-2 sm:w-40">
                            <div data-card-qr class="aspect-square w-full text-center text-xs text-slate-500">Chargement…</div>
                        </div>
                        <div class="min-w-0 flex-1 space-y-3 text-sm">
                            <div>
                                <p class="text-[11px] uppercase tracking-widest text-white/70">N° de carte</p>
                                <p class="font-mono text-base font-semibold tracking-wider">{{ $card->number }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] uppercase tracking-widest text-white/70">Valable jusqu’au</p>
                                <p class="font-semibold">{{ $card->expires_at?->translatedFormat('d F Y') ?? '—' }}</p>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-white/20" aria-hidden="true"><span data-card-bar class="block h-full w-full origin-left rounded-full bg-white transition-transform duration-1000 ease-linear"></span></div>
                            <p data-card-status class="text-xs text-white/80" role="status" aria-live="polite">Code actualisé automatiquement.</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if($past->isNotEmpty())
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft sm:p-6">
                <h2 class="font-display text-lg font-semibold text-slate-900">Cartes précédentes</h2>
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach($past as $card)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                            <span>
                                <span class="font-semibold text-slate-800">{{ $card->membership?->node?->title ?? ($scopeLabels[$card->membership?->scope] ?? 'Fondation') }}</span>
                                <span class="text-slate-500"> · mandat {{ $card->mandate?->label }} · {{ $card->number }}</span>
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ $statuses[$card->status] ?? $card->status }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</div>
@vite(['resources/js/member-card.js'])
@endsection
