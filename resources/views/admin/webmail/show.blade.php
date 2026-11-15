@extends('admin.layouts.app')

@section('title', $message['subject'])
@section('section', 'Webmail')

@section('content')
    @php
        $format = fn (array $a) => $a['name'] !== '' ? $a['name'].' <'.$a['email'].'>' : $a['email'];
        $date = $message['date'] ? \Carbon\Carbon::parse($message['date'])->timezone(config('app.timezone'))->format('d/m/Y à H:i') : '';
        $csp = "default-src 'none'; img-src data:; style-src 'unsafe-inline'";
        $document = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="'.$csp.'"><base target="_blank"><style>body{margin:0;padding:14px;font:14px/1.5 Arial,sans-serif;color:#222;word-wrap:break-word}img{max-width:100%;height:auto}a{color:#8a1538}pre{white-space:pre-wrap}</style></head><body>'
            .($html ?? '<pre>'.e($message['text'] ?? '').'</pre>').'</body></html>';
    @endphp

    <x-admin.page-header :title="$message['subject']" :subtitle="$mailbox->address">
        <x-slot:actions>
            <a href="{{ route('admin.webmail.mail.index', ['boite' => $mailbox->id, 'dossier' => $folder]) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
            @if($canWrite)
                <a href="{{ route('admin.webmail.mail.compose', ['mailbox' => $mailbox, 'mode' => 'reply', 'uid' => $message['uid'], 'dossier' => $folder]) }}" class="btn btn-primary">Répondre</a>
                <a href="{{ route('admin.webmail.mail.compose', ['mailbox' => $mailbox, 'mode' => 'replyall', 'uid' => $message['uid'], 'dossier' => $folder]) }}" class="btn btn-secondary">Répondre à tous</a>
                <a href="{{ route('admin.webmail.mail.compose', ['mailbox' => $mailbox, 'mode' => 'forward', 'uid' => $message['uid'], 'dossier' => $folder]) }}" class="btn btn-secondary">Transférer</a>
                @if(\App\Support\Webmail\FolderRoles::detect($folder) === 'drafts')
                    <a href="{{ route('admin.webmail.mail.compose', ['mailbox' => $mailbox, 'mode' => 'draft', 'uid' => $message['uid'], 'dossier' => $folder]) }}" class="btn btn-secondary">Reprendre le brouillon</a>
                @endif
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card">
        <div class="card-body stack" style="gap:6px">
            <div><strong>De :</strong> {{ $format($message['from']) }}</div>
            <div><strong>À :</strong> {{ collect($message['to'])->map($format)->implode(', ') ?: '—' }}</div>
            @if($message['cc'])<div><strong>Cc :</strong> {{ collect($message['cc'])->map($format)->implode(', ') }}</div>@endif
            <div class="text-muted">{{ $date }}</div>
            <p class="text-muted text-sm" style="margin:0">Les images distantes et les scripts sont bloqués pour votre sécurité.</p>
        </div>
        <iframe sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" srcdoc="{{ $document }}" title="Contenu du message" style="width:100%;height:560px;border:0;border-top:1px solid var(--line);background:#fff"></iframe>
        @if(! empty($message['attachments']))
            <div class="card-body" style="border-top:1px solid var(--line)">
                <div class="text-muted text-sm" style="margin-bottom:8px;font-weight:700">Pièces jointes</div>
                <div class="chips">
                    @foreach($message['attachments'] as $file)
                        <a class="chip" href="{{ route('admin.webmail.mail.attachment', ['mailbox' => $mailbox, 'uid' => $message['uid'], 'index' => $file['index'], 'dossier' => $folder]) }}"><x-admin.icon name="paperclip" :size="13" /> {{ $file['name'] }}@if($file['size']) ({{ number_format($file['size'] / 1024, 0, ',', ' ') }} Ko)@endif</a>
                    @endforeach
                </div>
            </div>
        @endif
    </article>

    @if($canUpdate)
        <article class="card" style="margin-top:16px">
            <div class="card-body" style="display:flex;gap:18px;flex-wrap:wrap;align-items:end">
                @if($labels->count())
                    <form method="post" action="{{ route('admin.webmail.mail.tag', ['mailbox' => $mailbox, 'uid' => $message['uid']]) }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                        @csrf
                        <input type="hidden" name="dossier" value="{{ $folder }}">
                        <strong>Étiquettes</strong>
                        @foreach($labels as $label)
                            <label class="check"><input type="checkbox" name="labels[]" value="{{ $label->id }}" @checked(in_array($label->id, $assigned, true))> {{ $label->name }}</label>
                        @endforeach
                        <button class="btn btn-secondary btn-sm" type="submit">Enregistrer</button>
                    </form>
                @endif
                <form method="post" action="{{ route('admin.webmail.mail.mark', ['mailbox' => $mailbox, 'uid' => $message['uid']]) }}" style="display:flex;gap:8px">
                    @csrf
                    <input type="hidden" name="dossier" value="{{ $folder }}">
                    <input type="hidden" name="flag" value="seen">
                    <input type="hidden" name="on" value="0">
                    <button class="btn btn-secondary btn-sm" type="submit">Marquer comme non lu</button>
                </form>
                <form method="post" action="{{ route('admin.webmail.mail.move', ['mailbox' => $mailbox, 'uid' => $message['uid']]) }}" style="display:flex;gap:8px;align-items:center">
                    @csrf
                    <input type="hidden" name="dossier" value="{{ $folder }}">
                    <select name="target" aria-label="Déplacer vers">
                        @foreach($folders->where('path', '!=', $folder) as $target)
                            <option value="{{ $target['path'] }}">{{ $target['role'] !== 'other' ? \App\Support\Webmail\FolderRoles::LABELS[$target['role']] : $target['name'] }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-secondary btn-sm" type="submit">Déplacer</button>
                </form>
                @if($canDelete)
                    <x-admin.delete-button :action="route('admin.webmail.mail.trash', ['mailbox' => $mailbox, 'uid' => $message['uid'], 'dossier' => $folder])" title="Supprimer ce message ?" message="Le message part à la corbeille (suppression définitive s’il y est déjà)." />
                @endif
            </div>
        </article>
    @endif
@endsection
