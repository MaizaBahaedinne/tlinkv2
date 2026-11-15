@extends('layouts.base', ['title' => $message['subject']])

@section('content')
@php
    $format = fn (array $a) => $a['name'] !== '' ? $a['name'].' <'.$a['email'].'>' : $a['email'];
    $date = $message['date'] ? \Carbon\Carbon::parse($message['date'])->timezone(config('app.timezone'))->format('d/m/Y à H:i') : '';
    $csp = "default-src 'none'; img-src data:; style-src 'unsafe-inline'";
    $document = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="'.$csp.'"><base target="_blank"><style>body{margin:0;padding:14px;font:14px/1.5 Arial,sans-serif;color:#222;word-wrap:break-word}img{max-width:100%;height:auto}a{color:#8a1538}pre{white-space:pre-wrap}</style></head><body>'
        .($html ?? '<pre>'.e($message['text'] ?? '').'</pre>').'</body></html>';
    $btnPrimary = 'inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90';
    $btnSecondary = 'inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50';
    $compose = fn (string $mode) => route('webmail.compose', ['mailbox' => $mailbox, 'mode' => $mode, 'uid' => $message['uid'], 'dossier' => $folder]);
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-4xl space-y-4 py-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="font-display text-xl font-bold text-slate-900">{{ $message['subject'] }}</h1>
                <p class="text-sm text-slate-500">{{ $mailbox->address }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('webmail.index', ['boite' => $mailbox->id, 'dossier' => $folder]) }}" class="{{ $btnSecondary }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour</a>
                @if($canWrite)
                    <a href="{{ $compose('reply') }}" class="{{ $btnPrimary }}">Répondre</a>
                    <a href="{{ $compose('replyall') }}" class="{{ $btnSecondary }}">Répondre à tous</a>
                    <a href="{{ $compose('forward') }}" class="{{ $btnSecondary }}">Transférer</a>
                    @if(\App\Support\Webmail\FolderRoles::detect($folder) === 'drafts')
                        <a href="{{ $compose('draft') }}" class="{{ $btnSecondary }}">Reprendre le brouillon</a>
                    @endif
                @endif
            </div>
        </div>

        @include('webmail._flash')

        <article class="overflow-hidden rounded-2xl bg-white shadow-soft">
            <div class="space-y-1 p-4 text-sm">
                <p><strong>De :</strong> {{ $format($message['from']) }}</p>
                <p><strong>À :</strong> {{ collect($message['to'])->map($format)->implode(', ') ?: '—' }}</p>
                @if($message['cc'])<p><strong>Cc :</strong> {{ collect($message['cc'])->map($format)->implode(', ') }}</p>@endif
                <p class="text-slate-500">{{ $date }}</p>
                <p class="text-xs text-slate-400">Les images distantes et les scripts sont bloqués pour votre sécurité.</p>
            </div>
            <iframe sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="{{ $document }}" title="Contenu du message" class="h-[560px] w-full border-0 border-t border-slate-100 bg-white"></iframe>
            @if(! empty($message['attachments']))
                <div class="border-t border-slate-100 p-4">
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
            <div class="flex flex-wrap items-end gap-5 rounded-2xl bg-white p-4 shadow-soft">
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
                    <button class="{{ $btnSecondary }}" type="submit">Marquer comme non lu</button>
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
                @if($canDelete)
                    <form method="post" action="{{ route('webmail.trash', ['mailbox' => $mailbox, 'uid' => $message['uid'], 'dossier' => $folder]) }}" onsubmit="return confirm('Supprimer ce message ? Il part à la corbeille (suppression définitive s’il y est déjà).')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Supprimer</button>
                    </form>
                @endif
            </div>
        @endif
    </main>
</div>
@endsection
