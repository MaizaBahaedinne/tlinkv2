@extends('layouts.base', ['title' => 'Parrainage | T.Link'])

@section('content')
@php
    $input = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10';
    $btn = 'inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50';
@endphp
<div class="min-h-screen bg-tlink-sand pb-24 text-slate-800 lg:pb-12">
    @include('partials.app-header')

    <main class="app-container mx-auto max-w-4xl space-y-6 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Parrainage</h1>
            <p class="max-w-2xl text-sm text-slate-600">Appelez des personnes à rejoindre les postes situés sous le vôtre : choisissez un poste, puis partagez son lien, son QR code ou une invitation par e-mail. Chaque candidature est examinée par l’équipe avant toute attribution.</p>
        </div>

        @if(session('status'))
            <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
        @endif
        @if($errors->any())
            <p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $errors->first() }}</p>
        @endif

        @forelse($offices as $office)
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft sm:p-6" aria-label="Postes sous {{ $office['node']->title }}">
                <h2 class="font-display text-lg font-semibold text-slate-900">Sous mon poste : {{ $office['node']->title }}</h2>

                @if($office['positions']->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">Aucun poste n’est rattaché sous celui-ci pour le moment.</p>
                @else
                    <ul class="mt-4 divide-y divide-slate-100">
                        @foreach($office['positions'] as $row)
                            @php
                                $node = $row['node'];
                                $key = 'p'.$node->id;
                                $text = 'Rejoins l’équipe « '.$node->title.' »'.($row['scope'] ? ' ('.$row['scope'].')' : '').' de la Fondation Tunivisions : '.$row['url'];
                            @endphp
                            <li class="py-3" style="padding-left: {{ min(($row['depth'] - 1), 4) * 1.25 }}rem">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900">{{ $node->title }}@if($row['scope']) <span class="ml-1 rounded-full bg-tlink-blue/10 px-2 py-0.5 text-xs font-semibold text-tlink-blue">{{ $row['scope'] }}</span>@endif</p>
                                        <p class="text-xs text-slate-500">
                                            @if($row['closed'])
                                                Pourvu{{ $row['holders'] ? ' : '.($row['holders'][0]['name'] ?? '') : '' }}
                                            @elseif($row['holders'])
                                                {{ count($row['holders']) }} titulaire(s) · ouvert à d’autres personnes
                                            @else
                                                Poste vacant
                                            @endif
                                        </p>
                                    </div>
                                    @if($row['closed'])
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">Candidatures fermées</span>
                                    @endif
                                </div>

                                @unless($row['closed'])
                                    <details class="group mt-2 rounded-2xl border border-slate-200 bg-slate-50" data-sponsor-panel>
                                        <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-2.5 text-sm font-semibold text-tlink-blue [&::-webkit-details-marker]:hidden"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Partager ce poste <i class="fa-solid fa-chevron-down ml-auto text-xs transition group-open:rotate-180" aria-hidden="true"></i></summary>
                                        <div class="grid gap-5 border-t border-slate-200 p-4 md:grid-cols-[1fr_auto]">
                                            <div class="space-y-4">
                                                <div>
                                                    <label for="link-{{ $key }}" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Lien direct</label>
                                                    <input id="link-{{ $key }}" type="text" readonly value="{{ $row['url'] }}" class="{{ $input }} font-mono text-xs" onfocus="this.select()">
                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        <button type="button" class="{{ $btn }}" data-sponsor-copy="link-{{ $key }}"><i class="fa-regular fa-copy" aria-hidden="true"></i> Copier le lien</button>
                                                        <button type="button" class="{{ $btn }}" data-sponsor-share data-title="{{ $node->title }}" data-text="{{ $text }}" data-url="{{ $row['url'] }}" hidden><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Partager…</button>
                                                        <a class="{{ $btn }}" href="https://wa.me/?text={{ rawurlencode($text) }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
                                                        <a class="{{ $btn }}" href="mailto:?subject={{ rawurlencode('Rejoins la Fondation Tunivisions : '.$node->title) }}&body={{ rawurlencode($text) }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Mon application mail</a>
                                                    </div>
                                                </div>

                                                <form method="post" action="{{ route('sponsorship.invite', $node) }}" class="space-y-2 rounded-xl border border-slate-200 bg-white p-3">
                                                    @csrf
                                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Inviter par e-mail</p>
                                                    <div class="grid gap-2 sm:grid-cols-2">
                                                        <input name="first_name" type="text" required maxlength="120" placeholder="Prénom" aria-label="Prénom" class="{{ $input }}">
                                                        <input name="last_name" type="text" required maxlength="120" placeholder="Nom" aria-label="Nom" class="{{ $input }}">
                                                    </div>
                                                    <input name="email" type="email" required maxlength="255" placeholder="Adresse e-mail" aria-label="Adresse e-mail" class="{{ $input }}">
                                                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"><i class="fa-solid fa-paper-plane text-xs" aria-hidden="true"></i> Envoyer l’invitation</button>
                                                </form>
                                            </div>

                                            <div class="mx-auto w-44 space-y-2 text-center">
                                                <div data-sponsor-qr="{{ $row['url'] }}" data-alt="QR code du poste {{ $node->title }}" class="aspect-square w-full overflow-hidden rounded-2xl border border-slate-200 bg-white text-xs text-slate-400"><span class="grid h-full place-items-center">Génération…</span></div>
                                                <a data-sponsor-download href="#" download="qr-{{ \Illuminate\Support\Str::slug($node->title) }}.png" class="{{ $btn }} w-full" hidden><i class="fa-solid fa-download" aria-hidden="true"></i> Télécharger le QR</a>
                                            </div>
                                        </div>
                                    </details>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                    @if($office['truncated'])<p class="mt-3 text-xs text-slate-500">La liste est limitée : seuls les premiers postes sont affichés.</p>@endif
                @endif
            </section>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">Le parrainage est réservé aux membres qui occupent un poste. Vous n’en occupez pas pour le moment.</p>
        @endforelse
    </main>
</div>
@vite(['resources/js/sponsorship.js'])
@endsection
