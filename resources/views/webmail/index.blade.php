@extends('layouts.base', ['title' => 'T.Link Webmail'])

@section('content')
@php
    $route = fn (array $extra = []) => route('webmail.index', array_filter(['boite' => $mailbox?->id, 'dossier' => $folder] + $extra, fn ($value) => $value !== null && $value !== ''));
    $btnPrimary = 'inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90';
    $btnSecondary = 'inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50';
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-4 py-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold text-slate-900">Webmail</h1>
                <p class="text-sm text-slate-500">Lisez et envoyez les e-mails de votre entité depuis sa boîte partagée.</p>
            </div>
            @if($mailbox && $canWrite)
                <a href="{{ route('webmail.compose', $mailbox) }}" class="{{ $btnPrimary }}"><i class="fa-solid fa-pen" aria-hidden="true"></i> Écrire</a>
            @endif
        </div>

        @include('webmail._flash')

        @if($mailbox === null)
            <div class="rounded-2xl bg-white p-8 text-center shadow-soft">
                <i class="fa-regular fa-envelope text-3xl text-slate-300" aria-hidden="true"></i>
                <h2 class="mt-3 font-semibold text-slate-900">Aucune boîte à lire</h2>
                <p class="mt-1 text-sm text-slate-500">Aucune boîte e-mail n’est rattachée à votre entité, ou votre poste n’y donne pas accès.</p>
            </div>
        @else
            <div class="grid items-start gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]">
                <aside class="overflow-hidden rounded-2xl bg-white shadow-soft">
                    <div class="space-y-2 p-4">
                        @if($mailboxes->count() > 1)
                            <form method="get">
                                <select name="boite" onchange="this.form.submit()" aria-label="Boîte" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                    @foreach($mailboxes as $item)
                                        <option value="{{ $item->id }}" @selected($item->id === $mailbox->id)>{{ $item->title() }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @else
                            <p class="font-semibold text-slate-900">{{ $mailbox->title() }}</p>
                        @endif
                        <p class="break-all text-xs text-slate-500">{{ $mailbox->address }}</p>
                    </div>
                    <nav class="grid border-t border-slate-100" aria-label="Dossiers">
                        @forelse($folders as $item)
                            @php $current = ! $activeLabel && $item['path'] === $folder; @endphp
                            <a href="{{ route('webmail.index', ['boite' => $mailbox->id, 'dossier' => $item['path']]) }}" @if($current) aria-current="page" @endif
                               class="flex items-center justify-between px-4 py-2.5 text-sm transition {{ $current ? 'bg-rose-50 font-semibold text-tlink-blue' : 'text-slate-600 hover:bg-slate-50' }}">
                                <span>{{ $item['role'] !== 'other' ? $roleLabels[$item['role']] : $item['name'] }}</span>
                                @if($item['unseen'] > 0)<span class="rounded-full bg-tlink-blue px-2 text-[11px] font-bold leading-5 text-white">{{ $item['unseen'] }}</span>@endif
                            </a>
                        @empty
                            <span class="px-4 py-3 text-sm text-slate-500">Dossiers indisponibles.</span>
                        @endforelse
                    </nav>
                    @if($labels->count())
                        <div class="border-t border-slate-100 p-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Étiquettes</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($labels as $label)
                                    <a href="{{ $route(['etiquette' => $label->id]) }}" class="rounded-full border px-2.5 py-0.5 text-xs {{ $activeLabel?->id === $label->id ? 'border-tlink-blue text-tlink-blue' : 'border-slate-200 text-slate-600 hover:bg-slate-50' }}">{{ $label->name }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="border-t border-slate-100 p-4">
                        <a href="{{ route('webmail.settings', $mailbox) }}" class="{{ $btnSecondary }} w-full justify-center"><i class="fa-solid fa-gear" aria-hidden="true"></i> Signature, étiquettes, contacts</a>
                    </div>
                </aside>

                <section class="overflow-hidden rounded-2xl bg-white shadow-soft">
                    <form method="get" class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4">
                        <input type="hidden" name="boite" value="{{ $mailbox->id }}">
                        <input type="hidden" name="dossier" value="{{ $folder }}">
                        <div class="min-w-[12rem] flex-1">
                            <label for="q" class="mb-1 block text-xs font-medium text-slate-500">Rechercher dans ce dossier</label>
                            <input id="q" type="search" name="q" value="{{ $search }}" maxlength="80" placeholder="Expéditeur, objet, contenu…" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-tlink-blue focus:outline-none">
                        </div>
                        <button class="{{ $btnSecondary }}" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Rechercher</button>
                        @if($activeLabel)<span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-medium text-tlink-blue">Étiquette : {{ $activeLabel->name }}</span>@endif
                    </form>

                    @if($messages && $messages->count())
                        <ul class="divide-y divide-slate-100">
                            @foreach($messages as $message)
                                @php
                                    $isOut = in_array(\App\Support\Webmail\FolderRoles::detect($folder), ['sent', 'drafts'], true);
                                    $who = $isOut ? ($message['to'][0] ?? null) : $message['from'];
                                    $show = route('webmail.show', ['mailbox' => $mailbox, 'uid' => $message['uid'], 'dossier' => $folder]);
                                @endphp
                                <li class="flex items-start gap-2 px-3 py-3 hover:bg-slate-50">
                                    @if($canUpdate)
                                        <form method="post" action="{{ route('webmail.mark', ['mailbox' => $mailbox, 'uid' => $message['uid']]) }}">
                                            @csrf
                                            <input type="hidden" name="dossier" value="{{ $folder }}">
                                            <input type="hidden" name="flag" value="flagged">
                                            <input type="hidden" name="on" value="{{ $message['flagged'] ? 0 : 1 }}">
                                            <button type="submit" class="grid h-8 w-8 place-items-center rounded-lg {{ $message['flagged'] ? 'text-amber-500' : 'text-slate-300 hover:text-slate-500' }}" title="{{ $message['flagged'] ? 'Retirer l’étoile' : 'Marquer d’une étoile' }}" aria-label="Étoile"><i class="fa-solid fa-star" aria-hidden="true"></i></button>
                                        </form>
                                    @elseif($message['flagged'])
                                        <span class="grid h-8 w-8 place-items-center text-amber-500"><i class="fa-solid fa-star" aria-hidden="true"></i></span>
                                    @endif
                                    <a href="{{ $show }}" class="min-w-0 flex-1">
                                        <span class="block truncate text-sm {{ $message['seen'] ? 'text-slate-600' : 'font-bold text-slate-900' }}">{{ $who ? ($who['name'] ?: $who['email']) : '—' }}</span>
                                        <span class="block truncate text-sm {{ $message['seen'] ? 'text-slate-500' : 'font-semibold text-slate-800' }}">
                                            {{ $message['subject'] }}
                                            @if($message['attachments'])<i class="fa-solid fa-paperclip ml-1 text-xs" aria-hidden="true"></i>@endif
                                            @foreach($labelMap[$message['uid']] ?? [] as $label)<span class="ml-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] text-tlink-blue">{{ $label->name }}</span>@endforeach
                                        </span>
                                    </a>
                                    <span class="hidden shrink-0 pt-1 text-xs text-slate-400 sm:block">{{ $message['date'] ? \Carbon\Carbon::parse($message['date'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="p-4">{{ $messages->links() }}</div>
                    @elseif($messages)
                        <p class="p-10 text-center text-sm text-slate-500">{{ $search !== '' ? 'Aucun message ne correspond à cette recherche.' : 'Ce dossier est vide.' }}</p>
                    @else
                        <p class="p-10 text-center text-sm text-slate-500">La boîte ne répond pas pour le moment. Réessayez dans un instant ou prévenez l’administrateur.</p>
                    @endif
                </section>
            </div>
        @endif
    </main>
</div>
@endsection
