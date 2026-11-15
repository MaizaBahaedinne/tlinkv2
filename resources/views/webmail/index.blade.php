@extends('layouts.base', ['title' => 'T.Link Webmail'])

@section('content')
@php
    $route = fn (array $extra = []) => route('webmail.index', array_filter(['boite' => $mailbox?->id, 'dossier' => $folder] + $extra, fn ($value) => $value !== null && $value !== ''));
    $btnSecondary = 'inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50';
    $folderIcons = ['inbox' => 'fa-inbox', 'sent' => 'fa-paper-plane', 'drafts' => 'fa-file-pen', 'trash' => 'fa-trash-can', 'junk' => 'fa-ban', 'archive' => 'fa-box-archive'];
    $isOut = $mailbox ? in_array(\App\Support\Webmail\FolderRoles::detect($folder), ['sent', 'drafts'], true) : false;
@endphp
<div class="mg-shell bg-white text-slate-800">
    @include('partials.app-header')

    @if($mailbox === null)
        <main class="grid place-items-center bg-tlink-sand p-6">
            <div class="max-w-md rounded-2xl bg-white p-8 text-center shadow-soft">
                <i class="fa-regular fa-envelope text-3xl text-slate-300" aria-hidden="true"></i>
                <h2 class="mt-3 font-semibold text-slate-900">Aucune boîte à lire</h2>
                <p class="mt-1 text-sm text-slate-500">Aucune boîte e-mail n’est rattachée à votre entité, ou votre poste n’y donne pas accès.</p>
            </div>
        </main>
    @else
        <main class="flex min-h-0">
            {{-- Colonne de gauche (ordinateur) : écrire, boîte, dossiers, étiquettes. --}}
            <aside class="hidden w-64 shrink-0 flex-col overflow-y-auto border-r border-slate-200 bg-slate-50 lg:flex" aria-label="Dossiers">
                <div class="space-y-3 p-4">
                    @if($canWrite)
                        <a href="{{ route('webmail.compose', $mailbox) }}" class="flex items-center justify-center gap-2 rounded-2xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white shadow-soft transition hover:opacity-90"><i class="fa-solid fa-pen" aria-hidden="true"></i> Écrire</a>
                    @endif
                    @if($mailboxes->count() > 1)
                        <form method="get">
                            <select name="boite" onchange="this.form.submit()" aria-label="Boîte" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                                @foreach($mailboxes as $item)
                                    <option value="{{ $item->id }}" @selected($item->id === $mailbox->id)>{{ $item->title() }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    <div class="flex items-center gap-2.5">
                        <x-webmail-avatar :name="$mailbox->title()" :email="$mailbox->address" size="h-9 w-9 text-xs" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $mailbox->title() }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $mailbox->address }}</p>
                        </div>
                    </div>
                </div>

                <nav class="grid gap-0.5 px-2" aria-label="Dossiers de la boîte">
                    @forelse($folders as $item)
                        @php $current = ! $activeLabel && $item['path'] === $folder; @endphp
                        <a href="{{ route('webmail.index', ['boite' => $mailbox->id, 'dossier' => $item['path']]) }}" @if($current) aria-current="page" @endif
                           class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition {{ $current ? 'bg-rose-100 font-semibold text-tlink-blue' : 'text-slate-600 hover:bg-slate-200/60' }}">
                            <i class="fa-solid {{ $folderIcons[$item['role']] ?? 'fa-folder' }} w-4 text-center" aria-hidden="true"></i>
                            <span class="min-w-0 flex-1 truncate">{{ $item['role'] !== 'other' ? $roleLabels[$item['role']] : $item['name'] }}</span>
                            @if($item['unseen'] > 0)<span class="rounded-full bg-tlink-blue px-2 text-[11px] font-bold leading-5 text-white">{{ $item['unseen'] }}</span>@endif
                        </a>
                    @empty
                        <span class="px-3 py-2 text-sm text-slate-500">Dossiers indisponibles.</span>
                    @endforelse
                </nav>

                @if($labels->count())
                    <div class="mt-4 px-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Étiquettes</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($labels as $label)
                                <a href="{{ $route(['etiquette' => $label->id]) }}" class="rounded-full border px-2.5 py-0.5 text-xs {{ $activeLabel?->id === $label->id ? 'border-tlink-blue bg-white text-tlink-blue' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100' }}">{{ $label->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-auto p-3">
                    <a href="{{ route('webmail.settings', $mailbox) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-200/60"><i class="fa-solid fa-gear w-4 text-center" aria-hidden="true"></i> Signature, étiquettes, contacts</a>
                </div>
            </aside>

            {{-- Liste des messages : occupe toute la place restante, seule elle défile. --}}
            <section class="flex min-w-0 flex-1 flex-col" aria-label="Messages">
                <form method="get" class="flex flex-none flex-wrap items-center gap-2 border-b border-slate-200 px-3 py-2.5 sm:px-4">
                    <input type="hidden" name="boite" value="{{ $mailbox->id }}">
                    <input type="hidden" name="dossier" value="{{ $folder }}">
                    <div class="relative min-w-[10rem] flex-1">
                        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                        <input type="search" name="q" value="{{ $search }}" maxlength="80" placeholder="Expéditeur, objet, contenu…" aria-label="Rechercher dans ce dossier" class="w-full rounded-xl border border-transparent bg-slate-100 py-2 pl-9 pr-3 text-sm outline-none transition focus:border-tlink-blue focus:bg-white">
                    </div>
                    @if($activeLabel)<a href="{{ route('webmail.index', ['boite' => $mailbox->id, 'dossier' => $folder]) }}" class="rounded-full bg-rose-50 px-3 py-1 text-xs font-medium text-tlink-blue" title="Retirer le filtre">Étiquette : {{ $activeLabel->name }} <i class="fa-solid fa-xmark ml-1" aria-hidden="true"></i></a>@endif
                    <a href="{{ $route() }}" class="{{ $btnSecondary }}" title="Actualiser" aria-label="Actualiser"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></a>
                    @if($canWrite)<a href="{{ route('webmail.compose', $mailbox) }}" class="{{ $btnSecondary }} lg:hidden" aria-label="Écrire"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>@endif
                </form>

                {{-- Mobile et tablette : dossiers en pastilles défilantes. --}}
                <nav class="flex flex-none gap-2 overflow-x-auto border-b border-slate-200 px-3 py-2 lg:hidden" aria-label="Dossiers de la boîte">
                    @foreach($folders as $item)
                        @php $current = ! $activeLabel && $item['path'] === $folder; @endphp
                        <a href="{{ route('webmail.index', ['boite' => $mailbox->id, 'dossier' => $item['path']]) }}" @if($current) aria-current="page" @endif
                           class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium {{ $current ? 'border-tlink-blue bg-rose-50 text-tlink-blue' : 'border-slate-200 text-slate-600' }}">
                            {{ $item['role'] !== 'other' ? $roleLabels[$item['role']] : $item['name'] }}@if($item['unseen'] > 0)<span class="rounded-full bg-tlink-blue px-1.5 text-[10px] font-bold text-white">{{ $item['unseen'] }}</span>@endif
                        </a>
                    @endforeach
                    <a href="{{ route('webmail.settings', $mailbox) }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600"><i class="fa-solid fa-gear" aria-hidden="true"></i> Réglages</a>
                </nav>

                @if(session('status') || $errors->any() || $error)
                    <div class="flex-none space-y-2 border-b border-slate-200 p-3">@include('webmail._flash')</div>
                @endif

                <div class="min-h-0 flex-1 overflow-y-auto">
                    @if($messages && $messages->count())
                        <ul class="divide-y divide-slate-100">
                            @foreach($messages as $message)
                                @php
                                    $who = $isOut ? ($message['to'][0] ?? null) : $message['from'];
                                    $whoName = $who ? ($who['name'] ?: $who['email']) : '—';
                                    $when = $message['date'] ? \Carbon\Carbon::parse($message['date'])->timezone(config('app.timezone')) : null;
                                    $show = route('webmail.show', ['mailbox' => $mailbox, 'uid' => $message['uid'], 'dossier' => $folder]);
                                @endphp
                                <li class="group relative flex items-center gap-3 px-3 py-3 transition hover:bg-slate-50 sm:px-4 {{ $message['seen'] ? '' : 'bg-rose-50/40' }}">
                                    <a href="{{ $show }}" class="flex min-w-0 flex-1 items-center gap-3" aria-label="Ouvrir : {{ $message['subject'] }}">
                                        <span class="relative">
                                            <x-webmail-avatar :name="$who['name'] ?? ''" :email="$who['email'] ?? ''" size="h-11 w-11 text-sm" />
                                            @unless($message['seen'])<span class="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full border-2 border-white bg-tlink-blue" title="Non lu"></span>@endunless
                                        </span>
                                        <span class="grid min-w-0 flex-1 gap-0.5 md:grid-cols-[14rem_minmax(0,1fr)] md:items-center md:gap-4">
                                            <span class="truncate text-sm {{ $message['seen'] ? 'text-slate-600' : 'font-bold text-slate-900' }}">{{ $isOut && $who ? 'À : ' : '' }}{{ $whoName }}</span>
                                            <span class="flex min-w-0 items-center gap-2">
                                                <span class="truncate text-sm {{ $message['seen'] ? 'text-slate-500' : 'font-semibold text-slate-800' }}">{{ $message['subject'] }}</span>
                                                @if($message['attachments'])<i class="fa-solid fa-paperclip shrink-0 text-xs text-slate-400" title="Pièce jointe" aria-hidden="true"></i>@endif
                                                @foreach($labelMap[$message['uid']] ?? [] as $label)<span class="hidden shrink-0 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] text-tlink-blue sm:inline">{{ $label->name }}</span>@endforeach
                                            </span>
                                        </span>
                                        <span class="shrink-0 text-xs {{ $message['seen'] ? 'text-slate-400' : 'font-semibold text-slate-700' }}" title="{{ $when?->format('d/m/Y H:i') }}">
                                            {{ $when ? ($when->isToday() ? $when->format('H:i') : ($when->isCurrentYear() ? $when->translatedFormat('j M') : $when->format('d/m/Y'))) : '' }}
                                        </span>
                                    </a>
                                    @if($canUpdate)
                                        <form method="post" action="{{ route('webmail.mark', ['mailbox' => $mailbox, 'uid' => $message['uid']]) }}" class="shrink-0">
                                            @csrf
                                            <input type="hidden" name="dossier" value="{{ $folder }}">
                                            <input type="hidden" name="flag" value="flagged">
                                            <input type="hidden" name="on" value="{{ $message['flagged'] ? 0 : 1 }}">
                                            <button type="submit" class="grid h-9 w-9 place-items-center rounded-lg {{ $message['flagged'] ? 'text-amber-500' : 'text-slate-300 hover:text-slate-500' }}" title="{{ $message['flagged'] ? 'Retirer l’étoile' : 'Marquer d’une étoile' }}" aria-label="Étoile"><i class="fa-solid fa-star" aria-hidden="true"></i></button>
                                        </form>
                                    @elseif($message['flagged'])
                                        <span class="grid h-9 w-9 shrink-0 place-items-center text-amber-500"><i class="fa-solid fa-star" aria-hidden="true"></i></span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @elseif($messages)
                        <div class="grid h-full place-items-center p-10 text-center">
                            <div>
                                <i class="fa-regular fa-envelope-open text-4xl text-slate-300" aria-hidden="true"></i>
                                <p class="mt-3 text-sm text-slate-500">{{ $search !== '' ? 'Aucun message ne correspond à cette recherche.' : 'Ce dossier est vide.' }}</p>
                            </div>
                        </div>
                    @else
                        <div class="grid h-full place-items-center p-10 text-center">
                            <p class="max-w-sm text-sm text-slate-500">La boîte ne répond pas pour le moment. Réessayez dans un instant ou prévenez l’administrateur.</p>
                        </div>
                    @endif
                </div>

                @if($messages && $messages->hasPages())
                    <div class="flex-none border-t border-slate-200 px-4 py-2">{{ $messages->links() }}</div>
                @endif
            </section>
        </main>
    @endif
</div>
@endsection
