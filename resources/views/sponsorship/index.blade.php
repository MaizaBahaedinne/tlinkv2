@extends('layouts.base', ['title' => 'Parrainage | T.Link'])

@section('content')
@php
    $input = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10';
    $btn = 'inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50';
@endphp
<div class="min-h-screen bg-tlink-sand pb-24 text-slate-800 lg:pb-12">
    @include('partials.app-header')

    <main class="app-container space-y-5 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Parrainage</h1>
            <p class="max-w-3xl text-sm text-slate-600">Votre poste est à gauche, les postes situés sous le vôtre s’étendent vers la droite : une colonne par niveau. Cliquez sur un poste pour le partager (lien, QR code ou e-mail). Chaque candidature est examinée par l’équipe avant toute attribution.</p>
        </div>

        @if(session('status'))
            <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
        @endif
        @if($errors->any() && ! $selected)
            <p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $errors->first() }}</p>
        @endif

        @if($offices->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">Le parrainage est réservé aux membres qui occupent un poste. Vous n’en occupez pas pour le moment.</p>
        @else
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_23rem] lg:items-start">
                <div class="min-w-0 space-y-5">
                    {{-- Recherche d'un poste : les autres sont estompés, le premier résultat est centré. --}}
                    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                        <div class="relative min-w-[12rem] flex-1">
                            <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                            <input type="search" data-sp-search maxlength="80" placeholder="Chercher un poste ou un titulaire…" aria-label="Chercher un poste ou un titulaire" class="{{ $input }} pl-9">
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" data-sp-open> Postes ouverts uniquement</label>
                        <span data-sp-count class="text-xs text-slate-500" role="status" aria-live="polite"></span>
                    </div>

                    @foreach($offices as $office)
                        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft" aria-label="Postes sous {{ $office['node']->title }}">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                                <h2 class="font-display text-lg font-semibold text-slate-900">Sous mon poste : {{ $office['node']->title }}</h2>
                                <p class="flex flex-wrap gap-2 text-xs font-medium">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $office['count'] }} poste{{ $office['count'] > 1 ? 's' : '' }}</span>
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">{{ $office['open'] }} ouvert{{ $office['open'] > 1 ? 's' : '' }}</span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $office['depth'] }} niveau{{ $office['depth'] > 1 ? 'x' : '' }}</span>
                                </p>
                            </div>

                            @if($office['count'] === 0)
                                <p class="px-5 py-8 text-sm text-slate-500">Aucun poste n’est rattaché sous celui-ci pour le moment.</p>
                            @else
                                <div class="overflow-x-auto p-5">
                                    <div class="w-max">
                                        {{-- Bandeau des niveaux : une colonne par niveau, alignée sur l'organigramme. --}}
                                        <div class="mb-3 flex text-[11px] font-bold uppercase tracking-wider text-slate-400" aria-hidden="true">
                                            @for($level = 0; $level <= $office['depth']; $level++)
                                                <span class="mr-16 w-60 shrink-0 border-b-2 {{ $level === 0 ? 'border-tlink-blue text-tlink-blue' : 'border-slate-200' }} pb-1">{{ $level === 0 ? 'Mon poste' : 'Niveau −'.$level }}</span>
                                            @endfor
                                        </div>
                                        <div class="sp-tree" role="tree" aria-label="Organigramme sous {{ $office['node']->title }}">
                                            <ul>
                                                @include('sponsorship.node', ['item' => $office['tree'], 'selected' => $selected])
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($office['truncated'])<p class="px-5 pb-4 text-xs text-slate-500">L’équipe est très étendue : seuls les premiers postes sont affichés.</p>@endif
                        </section>
                    @endforeach
                </div>

                {{-- Panneau de partage du poste choisi. --}}
                <aside id="partage" class="scroll-mt-24 {{ $selected ? 'order-first lg:order-none' : '' }} lg:sticky lg:top-24" aria-label="Partager un poste">
                    @if(! $selected)
                        <div class="rounded-3xl border border-dashed border-slate-300 bg-white/70 p-6 text-center text-sm text-slate-500">
                            <i class="fa-solid fa-share-nodes text-2xl text-slate-300" aria-hidden="true"></i>
                            <p class="mt-2 font-semibold text-slate-700">Choisissez un poste</p>
                            <p class="mt-1">Cliquez sur un poste de l’organigramme pour obtenir son lien, son QR code ou envoyer une invitation par e-mail.</p>
                        </div>
                    @else
                        @php
                            $node = $selected['node'];
                            $text = 'Rejoins l’équipe « '.$node->title.' »'.($selected['scope'] ? ' ('.$selected['scope'].')' : '').' de la Fondation Tunivisions : '.$selected['url'];
                        @endphp
                        <div class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-soft" data-sponsor-panel>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Niveau −{{ $selected['depth'] }} · sous {{ $selected['office'] }}</p>
                                <h2 class="font-display text-lg font-semibold text-slate-900">{{ $node->title }}@if($selected['scope']) <span class="ml-1 rounded-full bg-tlink-blue/10 px-2 py-0.5 align-middle text-xs font-semibold text-tlink-blue">{{ $selected['scope'] }}</span>@endif</h2>
                                <p class="text-xs text-slate-500">
                                    @if($selected['closed'])
                                        Pourvu : {{ $selected['holders'][0]['name'] ?? '' }}
                                    @elseif($selected['holders'])
                                        {{ count($selected['holders']) }} titulaire(s) · ouvert à d’autres personnes
                                    @else
                                        Poste vacant
                                    @endif
                                </p>
                            </div>

                            @if($errors->any())
                                <p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert">{{ $errors->first() }}</p>
                            @endif

                            @if($selected['closed'])
                                <p class="rounded-xl bg-slate-50 px-3 py-3 text-sm text-slate-600"><i class="fa-solid fa-lock mr-1" aria-hidden="true"></i> Candidatures fermées : ce poste à titulaire unique est déjà pourvu.</p>
                            @else
                                <div class="mx-auto w-44 space-y-2 text-center">
                                    <div data-sponsor-qr="{{ $selected['url'] }}" data-alt="QR code du poste {{ $node->title }}" class="aspect-square w-full overflow-hidden rounded-2xl border border-slate-200 bg-white text-xs text-slate-400"><span class="grid h-full place-items-center">Génération…</span></div>
                                    <a data-sponsor-download href="#" download="qr-{{ \Illuminate\Support\Str::slug($node->title) }}.png" class="{{ $btn }} w-full" hidden><i class="fa-solid fa-download" aria-hidden="true"></i> Télécharger le QR</a>
                                </div>

                                <div>
                                    <label for="sp-link" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Lien direct</label>
                                    <input id="sp-link" type="text" readonly value="{{ $selected['url'] }}" class="{{ $input }} font-mono text-xs" onfocus="this.select()">
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button type="button" class="{{ $btn }}" data-sponsor-copy="sp-link"><i class="fa-regular fa-copy" aria-hidden="true"></i> Copier le lien</button>
                                        <button type="button" class="{{ $btn }}" data-sponsor-share data-title="{{ $node->title }}" data-text="{{ $text }}" data-url="{{ $selected['url'] }}" hidden><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Partager…</button>
                                        <a class="{{ $btn }}" href="https://wa.me/?text={{ rawurlencode($text) }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
                                        <a class="{{ $btn }}" href="mailto:?subject={{ rawurlencode('Rejoins la Fondation Tunivisions : '.$node->title) }}&body={{ rawurlencode($text) }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Mon application mail</a>
                                    </div>
                                </div>

                                <form method="post" action="{{ route('sponsorship.invite', $node) }}" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    @csrf
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Inviter par e-mail</p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <input name="first_name" type="text" required maxlength="120" placeholder="Prénom" aria-label="Prénom" class="{{ $input }}">
                                        <input name="last_name" type="text" required maxlength="120" placeholder="Nom" aria-label="Nom" class="{{ $input }}">
                                    </div>
                                    <input name="email" type="email" required maxlength="255" placeholder="Adresse e-mail" aria-label="Adresse e-mail" class="{{ $input }}">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"><i class="fa-solid fa-paper-plane text-xs" aria-hidden="true"></i> Envoyer l’invitation</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </aside>
            </div>
        @endif
    </main>
</div>
@vite(['resources/js/sponsorship.js'])
@endsection
