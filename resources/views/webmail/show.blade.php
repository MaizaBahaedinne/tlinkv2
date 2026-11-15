@extends('layouts.base', ['title' => $message['subject']])

@section('content')
@php
    $date = $message['date'] ? \Carbon\Carbon::parse($message['date'])->timezone(config('app.timezone'))->format('d/m/Y à H:i') : '';
    $csp = "default-src 'none'; img-src data:; style-src 'unsafe-inline'";
    $document = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="'.$csp.'"><base target="_blank"><style>body{margin:0;padding:18px;font:14px/1.5 Arial,sans-serif;color:#222;word-wrap:break-word}img{max-width:100%;height:auto}a{color:#8a1538}pre{white-space:pre-wrap}</style></head><body>'
        .($html ?? '<pre>'.e($message['text'] ?? '').'</pre>').'</body></html>';
    $btnPrimary = 'inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90';
    $btnSecondary = 'inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50';
    $compose = fn (string $mode) => route('webmail.compose', ['mailbox' => $mailbox, 'mode' => $mode, 'uid' => $message['uid'], 'dossier' => $folder]);
    $people = fn (array $list) => collect($list)->map(fn (array $a) => $a['name'] !== '' ? $a['name'].' <'.$a['email'].'>' : $a['email'])->implode(', ');
    $from = $message['from'];
@endphp
<div class="mg-shell bg-white text-slate-800">
    @include('partials.app-header')

    <main class="flex min-h-0 flex-col overflow-y-auto bg-slate-50">
        {{-- Barre d'actions. --}}
        <div class="sticky top-0 z-10 flex flex-none flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-3 py-2.5 sm:px-5">
            <a href="{{ route('webmail.index', ['boite' => $mailbox->id, 'dossier' => $folder]) }}" class="{{ $btnSecondary }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour</a>
            @if($canWrite)
                <a href="{{ $compose('reply') }}" class="{{ $btnPrimary }}"><i class="fa-solid fa-reply" aria-hidden="true"></i> Répondre</a>
                <a href="{{ $compose('replyall') }}" class="{{ $btnSecondary }}"><i class="fa-solid fa-reply-all" aria-hidden="true"></i> <span class="hidden sm:inline">Répondre à tous</span></a>
                <a href="{{ $compose('forward') }}" class="{{ $btnSecondary }}"><i class="fa-solid fa-share" aria-hidden="true"></i> <span class="hidden sm:inline">Transférer</span></a>
                @if(\App\Support\Webmail\FolderRoles::detect($folder) === 'drafts')
                    <a href="{{ $compose('draft') }}" class="{{ $btnSecondary }}">Reprendre le brouillon</a>
                @endif
            @endif
            @if($canDelete)
                <form method="post" action="{{ route('webmail.trash', ['mailbox' => $mailbox, 'uid' => $message['uid'], 'dossier' => $folder]) }}" class="ml-auto" onsubmit="return confirm('Supprimer ce message ? Il part à la corbeille (suppression définitive s’il y est déjà).')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> <span class="hidden sm:inline">Supprimer</span></button>
                </form>
            @endif
        </div>

        @if(session('status') || $errors->any())
            <div class="space-y-2 p-3 sm:px-5">@include('webmail._flash')</div>
        @endif

        {{-- Le message occupe toute la largeur et le reste de la hauteur. --}}
        <article class="flex min-h-[32rem] flex-1 flex-col bg-white">
            <div class="flex flex-none items-start gap-4 border-b border-slate-100 px-4 py-4 sm:px-6">
                <x-webmail-avatar :name="$from['name']" :email="$from['email']" size="h-12 w-12 text-base" />
                <div class="min-w-0 flex-1">
                    <h1 class="font-display text-xl font-bold leading-snug text-slate-900">{{ $message['subject'] }}</h1>
                    <p class="mt-1 text-sm"><strong class="text-slate-900">{{ $from['name'] !== '' ? $from['name'] : $from['email'] }}</strong>@if($from['name'] !== '') <span class="text-slate-500">&lt;{{ $from['email'] }}&gt;</span>@endif</p>
                    <p class="truncate text-xs text-slate-500" title="{{ $people($message['to']) }}">À : {{ $people($message['to']) ?: '—' }}@if($message['cc']) · Cc : {{ $people($message['cc']) }}@endif</p>
                </div>
                <div class="shrink-0 text-right text-xs text-slate-500">
                    <p>{{ $date }}</p>
                    <p class="mt-1">{{ $mailbox->address }}</p>
                </div>
            </div>
            <p class="flex-none bg-amber-50 px-4 py-1.5 text-xs text-amber-800 sm:px-6"><i class="fa-solid fa-shield-halved mr-1" aria-hidden="true"></i> Les images distantes et les scripts sont bloqués pour votre sécurité.</p>
            <iframe sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="{{ $document }}" title="Contenu du message" class="min-h-[24rem] w-full flex-1 border-0 bg-white"></iframe>
            @if(! empty($message['attachments']))
                <div class="flex-none border-t border-slate-100 px-4 py-3 sm:px-6">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Pièces jointes</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($message['attachments'] as $file)
                            <a class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-700 hover:bg-slate-50" href="{{ route('webmail.attachment', ['mailbox' => $mailbox, 'uid' => $message['uid'], 'index' => $file['index'], 'dossier' => $folder]) }}"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> {{ $file['name'] }}@if($file['size']) ({{ number_format($file['size'] / 1024, 0, ',', ' ') }} Ko)@endif</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>

        @if($canUpdate)
            <div class="flex flex-none flex-wrap items-end gap-5 border-t border-slate-200 bg-white px-4 py-3 sm:px-6">
                @if($labels->count())
                    <form method="post" action="{{ route('webmail.tag', ['mailbox' => $mailbox, 'uid' => $message['uid']]) }}" class="flex flex-wrap items-center gap-3 text-sm">
                        @csrf
                        <input type="hidden" name="dossier" value="{{ $folder }}">
                        <strong>Étiquettes</strong>
                        @foreach($labels as $label)
                            <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="labels[]" value="{{ $label->id }}" @checked(in_array($label->id, $assigned, true))> {{ $label->name }}</label>
                        @endforeach
                        <button class="{{ $btnSecondary }}" type="submit">Enregistrer</button>
                    </form>
                @endif
                <form method="post" action="{{ route('webmail.mark', ['mailbox' => $mailbox, 'uid' => $message['uid']]) }}">
                    @csrf
                    <input type="hidden" name="dossier" value="{{ $folder }}">
                    <input type="hidden" name="flag" value="seen">
                    <input type="hidden" name="on" value="0">
                    <button class="{{ $btnSecondary }}" type="submit"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Marquer comme non lu</button>
                </form>
                <form method="post" action="{{ route('webmail.move', ['mailbox' => $mailbox, 'uid' => $message['uid']]) }}" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="dossier" value="{{ $folder }}">
                    <select name="target" aria-label="Déplacer vers" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @foreach($folders->where('path', '!=', $folder) as $target)
                            <option value="{{ $target['path'] }}">{{ $target['role'] !== 'other' ? \App\Support\Webmail\FolderRoles::LABELS[$target['role']] : $target['name'] }}</option>
                        @endforeach
                    </select>
                    <button class="{{ $btnSecondary }}" type="submit">Déplacer</button>
                </form>
            </div>
        @endif
    </main>
</div>
@endsection
