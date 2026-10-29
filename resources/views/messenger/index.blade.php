@extends('layouts.base', ['title' => 'T.Link Messenger'])

@section('content')
<div class="min-h-screen bg-tlink-sand text-slate-800">
    @include('partials.app-header')

    <main class="w-full px-2 py-3 sm:px-4">
        <div id="messenger"
             data-active="{{ $activeId }}"
             data-csrf="{{ csrf_token() }}"
             data-me="{{ auth()->id() }}"
             data-url-list="{{ route('messenger.conversations') }}"
             data-url-members="{{ route('messenger.members') }}"
             data-url-direct="{{ route('messenger.direct', 0) }}"
             data-url-groups="{{ route('messenger.groups.store') }}"
             data-url-show="{{ route('messenger.show', 0) }}"
             data-url-block="{{ route('messenger.block', 0) }}"
             data-url-profile="{{ route('member.show', 0) }}"
             data-url-delete="{{ route('messenger.messages.destroy', 0) }}"
             @if($activeId)
                 data-url-messages="{{ route('messenger.messages', $activeId) }}"
                 data-url-send="{{ route('messenger.send', $activeId) }}"
                 data-url-read="{{ route('messenger.read', $activeId) }}"
                 data-url-typing="{{ route('messenger.typing', $activeId) }}"
                 data-url-leave="{{ route('messenger.leave', $activeId) }}"
             @endif
             class="grid h-[calc(100dvh-9rem)] min-h-[26rem] lg:h-[calc(100dvh-7rem)] overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft lg:grid-cols-[22rem_minmax(0,1fr)] xl:grid-cols-[26rem_minmax(0,1fr)]">

            {{-- Liste des conversations --}}
            <aside id="mg-list-pane" class="{{ $activeId ? 'hidden lg:flex' : 'flex' }} min-h-0 flex-col border-slate-200 lg:border-r">
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                    <h1 class="font-display text-lg font-semibold text-slate-900">Messages</h1>
                    <button type="button" id="mg-new" class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-3 py-2 text-sm font-semibold text-white hover:opacity-90">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Nouveau
                    </button>
                </div>
                <ul id="mg-list" class="min-h-0 flex-1 divide-y divide-slate-100 overflow-y-auto" aria-label="Conversations"></ul>
                <p id="mg-list-empty" hidden class="p-6 text-center text-sm text-slate-500">Aucune conversation pour le moment. Écrivez à un membre avec « Nouveau ».</p>
            </aside>

            {{-- Conversation ouverte --}}
            <section id="mg-thread-pane" class="{{ $activeId ? 'flex' : 'hidden lg:flex' }} min-h-0 flex-col">
                @if($activeId)
                    <header class="flex items-center gap-3 border-b border-slate-100 px-3 py-3 sm:px-4">
                        <a href="{{ route('messenger.index') }}" class="grid h-9 w-9 place-items-center rounded-xl text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Retour aux conversations"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                        <span id="mg-head-avatar" class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-full bg-rose-50 text-sm font-semibold text-tlink-blue" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p id="mg-head-title" class="truncate font-display text-base font-semibold text-slate-900">Conversation</p>
                            <p id="mg-head-sub" class="truncate text-xs text-slate-500" aria-live="polite"></p>
                        </div>
                        <details class="relative" id="mg-menu">
                            <summary class="grid h-9 w-9 cursor-pointer list-none place-items-center rounded-xl text-slate-500 hover:bg-slate-100 [&::-webkit-details-marker]:hidden" aria-label="Options de la conversation"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></summary>
                            <div id="mg-menu-items" class="absolute right-0 z-20 mt-1 w-56 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft"></div>
                        </details>
                    </header>

                    <div class="relative min-h-0 flex-1">
                        <div id="mg-messages" role="log" aria-live="polite" aria-label="Messages" class="h-full overflow-y-auto bg-slate-50/60 px-3 py-4 sm:px-5"></div>
                        <button type="button" id="mg-jump" hidden class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-slate-900/90 px-4 py-1.5 text-xs font-semibold text-white shadow-soft">Nouveaux messages <i class="fa-solid fa-arrow-down ml-1" aria-hidden="true"></i></button>
                    </div>

                    <div id="mg-typing" class="h-5 px-5 text-xs italic text-slate-500" aria-live="polite"></div>

                    <p id="mg-restriction" hidden class="border-t border-slate-100 bg-amber-50 px-4 py-3 text-center text-sm text-amber-900"></p>
                    <form id="mg-composer" class="border-t border-slate-100 p-3 sm:p-4" autocomplete="off">
                        <p id="mg-error" hidden class="mb-2 text-xs font-semibold text-red-600" role="alert"></p>
                        <p id="mg-file-chip" hidden class="mb-2 inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                            <i class="fa-solid fa-image" aria-hidden="true"></i> <span id="mg-file-name"></span>
                            <button type="button" id="mg-file-clear" class="text-slate-500 hover:text-slate-900" aria-label="Retirer l’image"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </p>
                        <div class="flex items-end gap-2">
                            <input type="file" id="mg-file" accept="image/png,image/jpeg,image/webp" class="hidden">
                            <button type="button" id="mg-attach" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-slate-500 hover:bg-slate-100" aria-label="Joindre une image"><i class="fa-solid fa-paperclip" aria-hidden="true"></i></button>
                            <label for="mg-input" class="sr-only">Votre message</label>
                            <textarea id="mg-input" rows="1" maxlength="4000" placeholder="Écrivez un message…" class="max-h-36 min-h-[2.75rem] flex-1 resize-none rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-tlink-blue"></textarea>
                            <button type="submit" id="mg-send" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-tlink-blue text-white hover:opacity-90 disabled:opacity-50" aria-label="Envoyer"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
                        </div>
                    </form>
                @else
                    <div class="grid flex-1 place-items-center p-8 text-center">
                        <div class="max-w-xs space-y-2">
                            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-rose-50 text-2xl text-tlink-blue"><i class="fa-regular fa-comments" aria-hidden="true"></i></span>
                            <h2 class="font-display text-lg font-semibold text-slate-900">T.Link Messenger</h2>
                            <p class="text-sm text-slate-500">Choisissez une conversation ou écrivez à un membre de la communauté.</p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </main>

    {{-- Nouvelle conversation --}}
    <dialog id="mg-dialog" class="m-auto w-[min(92vw,28rem)] rounded-3xl border border-slate-200 p-0 shadow-soft backdrop:bg-slate-900/50">
        <form id="mg-group-form" method="post" action="{{ route('messenger.groups.store') }}" class="space-y-4 p-5">
            @csrf
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold text-slate-900">Nouvelle conversation</h2>
                <button type="button" id="mg-dialog-close" class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Fermer"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>

            <div class="flex gap-2 text-sm font-semibold" role="radiogroup" aria-label="Type de conversation">
                <label class="flex-1 cursor-pointer rounded-xl border border-slate-200 px-3 py-2 text-center has-[:checked]:border-tlink-blue has-[:checked]:bg-rose-50 has-[:checked]:text-tlink-blue"><input type="radio" name="mode" value="direct" class="sr-only" checked> Message direct</label>
                <label class="flex-1 cursor-pointer rounded-xl border border-slate-200 px-3 py-2 text-center has-[:checked]:border-tlink-blue has-[:checked]:bg-rose-50 has-[:checked]:text-tlink-blue"><input type="radio" name="mode" value="group" class="sr-only"> Groupe</label>
            </div>

            <div id="mg-group-title" hidden>
                <label for="mg-title" class="text-sm font-semibold text-slate-700">Nom du groupe</label>
                <input id="mg-title" type="text" name="title" maxlength="120" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-tlink-blue">
            </div>

            <div>
                <label for="mg-search" class="text-sm font-semibold text-slate-700">Rechercher un membre</label>
                <input id="mg-search" type="search" maxlength="80" placeholder="Nom ou prénom…" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                <ul id="mg-results" class="mt-2 max-h-56 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-100" hidden></ul>
            </div>

            <ul id="mg-chips" class="flex flex-wrap gap-2"></ul>
            <p id="mg-dialog-error" hidden class="text-xs font-semibold text-red-600" role="alert"></p>

            <div id="mg-group-actions" hidden class="flex justify-end">
                <button type="submit" class="rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Créer le groupe</button>
            </div>
        </form>
    </dialog>
</div>
@vite(['resources/js/messenger.js'])
@endsection
