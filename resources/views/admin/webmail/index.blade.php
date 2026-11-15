@extends('admin.layouts.app')

@section('title', 'Webmail')
@section('section', 'Webmail')

@section('content')
    @php
        $route = fn (array $extra = []) => route('admin.webmail.mail.index', array_filter(['boite' => $mailbox?->id, 'dossier' => $folder] + $extra, fn ($value) => $value !== null && $value !== ''));
    @endphp
    <x-admin.page-header title="Webmail" subtitle="Lisez et envoyez les e-mails de votre entité depuis sa boîte partagée.">
        <x-slot:actions>
            @if($mailbox && $canWrite)
                <a href="{{ route('admin.webmail.mail.compose', $mailbox) }}" class="btn btn-primary"><x-admin.icon name="send" :size="16" /> Écrire</a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->has('webmail') || $error)
        <div class="alert alert-error" role="alert" style="margin-bottom:12px"><x-admin.icon name="x" /> <span>{{ $errors->first('webmail') ?: $error }}</span></div>
    @endif

    @if($mailbox === null)
        <article class="card"><x-admin.empty icon="mail" title="Aucune boîte à lire" text="Aucune boîte e-mail n’est rattachée à votre entité, ou votre poste n’y donne pas accès." /></article>
    @else
        <div class="grid-3-1" style="grid-template-columns:230px minmax(0,1fr);align-items:start">
            <aside class="card card-flush">
                <div class="card-body stack" style="gap:10px">
                    @if($mailboxes->count() > 1)
                        <form method="get">
                            <select name="boite" onchange="this.form.submit()" aria-label="Boîte">
                                @foreach($mailboxes as $item)
                                    <option value="{{ $item->id }}" @selected($item->id === $mailbox->id)>{{ $item->title() }}</option>
                                @endforeach
                            </select>
                        </form>
                    @else
                        <strong>{{ $mailbox->title() }}</strong>
                    @endif
                    <span class="text-muted text-sm" style="word-break:break-all">{{ $mailbox->address }}</span>
                </div>
                <nav style="display:grid;border-top:1px solid var(--line)">
                    @forelse($folders as $item)
                        <a href="{{ route('admin.webmail.mail.index', ['boite' => $mailbox->id, 'dossier' => $item['path']]) }}" class="tab {{ ! $activeLabel && $item['path'] === $folder ? 'is-active' : '' }}" style="margin:0;border-bottom:0;justify-content:space-between">
                            <span>{{ $item['role'] !== 'other' ? $roleLabels[$item['role']] : $item['name'] }}</span>
                            @if($item['unseen'] > 0)<span class="n badge badge-info">{{ $item['unseen'] }}</span>@endif
                        </a>
                    @empty
                        <span class="card-body text-muted text-sm">Dossiers indisponibles.</span>
                    @endforelse
                </nav>
                @if($labels->count())
                    <div class="card-body" style="border-top:1px solid var(--line)">
                        <div class="text-muted text-sm" style="margin-bottom:8px;font-weight:700">Étiquettes</div>
                        <div class="chips">
                            @foreach($labels as $label)
                                <a href="{{ $route(['etiquette' => $label->id]) }}" class="chip" style="{{ $activeLabel?->id === $label->id ? 'border-color:var(--navy-800)' : '' }}">{{ $label->name }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="card-body" style="border-top:1px solid var(--line)">
                    <a href="{{ route('admin.webmail.mail.settings', $mailbox) }}" class="btn btn-secondary btn-sm">Signature, étiquettes, contacts</a>
                </div>
            </aside>

            <article class="card card-flush">
                <form method="get" class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
                    <input type="hidden" name="boite" value="{{ $mailbox->id }}">
                    <input type="hidden" name="dossier" value="{{ $folder }}">
                    <div class="field" style="flex:1;min-width:200px">
                        <label for="q">Rechercher dans ce dossier</label>
                        <input id="q" type="search" name="q" value="{{ $search }}" maxlength="80" placeholder="Expéditeur, objet, contenu…">
                    </div>
                    <button class="btn btn-secondary" type="submit"><x-admin.icon name="search" :size="16" /> Rechercher</button>
                    @if($activeLabel)<span class="badge badge-info">Étiquette : {{ $activeLabel->name }}</span>@endif
                </form>

                @if($messages && $messages->count())
                    <div class="table-wrap">
                        <table class="tbl">
                            <tbody>
                                @foreach($messages as $message)
                                    @php
                                        $isOut = in_array(\App\Support\Webmail\FolderRoles::detect($folder), ['sent', 'drafts'], true);
                                        $who = $isOut ? ($message['to'][0] ?? null) : $message['from'];
                                        $show = route('admin.webmail.mail.show', ['mailbox' => $mailbox, 'uid' => $message['uid'], 'dossier' => $folder]);
                                    @endphp
                                    <tr>
                                        <td style="width:34px">
                                            @if($canUpdate)
                                                <form method="post" action="{{ route('admin.webmail.mail.mark', ['mailbox' => $mailbox, 'uid' => $message['uid']]) }}">
                                                    @csrf
                                                    <input type="hidden" name="dossier" value="{{ $folder }}">
                                                    <input type="hidden" name="flag" value="flagged">
                                                    <input type="hidden" name="on" value="{{ $message['flagged'] ? 0 : 1 }}">
                                                    <button type="submit" class="btn btn-icon btn-sm" title="{{ $message['flagged'] ? 'Retirer l’étoile' : 'Marquer d’une étoile' }}" aria-label="Étoile" style="{{ $message['flagged'] ? 'color:#e0a100' : 'color:var(--muted)' }}"><x-admin.icon name="star" :size="15" /></button>
                                                </form>
                                            @elseif($message['flagged'])
                                                <x-admin.icon name="star" :size="15" />
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ $show }}" style="display:block;color:inherit;text-decoration:none">
                                                <span class="cell-main" style="{{ $message['seen'] ? 'font-weight:500' : 'font-weight:800' }}">{{ $who ? ($who['name'] ?: $who['email']) : '—' }}</span>
                                                <div class="cell-sub" style="{{ $message['seen'] ? '' : 'font-weight:700;color:var(--text)' }}">
                                                    {{ $message['subject'] }}
                                                    @if($message['attachments'])<x-admin.icon name="paperclip" :size="13" />@endif
                                                    @foreach($labelMap[$message['uid']] ?? [] as $label)<span class="badge badge-info">{{ $label->name }}</span>@endforeach
                                                </div>
                                            </a>
                                        </td>
                                        <td class="hide-mobile" style="white-space:nowrap;text-align:right">{{ $message['date'] ? \Carbon\Carbon::parse($message['date'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-body">{{ $messages->links() }}</div>
                @elseif($messages)
                    <x-admin.empty icon="inbox" title="Aucun message" :text="$search !== '' ? 'Aucun message ne correspond à cette recherche.' : 'Ce dossier est vide.'" />
                @else
                    <x-admin.empty icon="mail" title="Boîte indisponible" text="La boîte ne répond pas pour le moment. Réessayez dans un instant ou prévenez l’administrateur." />
                @endif
            </article>
        </div>
    @endif
@endsection
