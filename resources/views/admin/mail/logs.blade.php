@extends('admin.layouts.app')

@section('title', 'Journal des envois')
@section('section', 'Messagerie')

@section('content')
    <x-admin.page-header title="Journal des e-mails envoyés" subtitle="Tous les e-mails envoyés par la plateforme, regroupés par adresse : confirmation d’adresse, invitations, messages de test. Le contenu des messages n’est pas conservé." />

    @include('admin.mail._tabs')

    <div class="stats" style="margin-bottom:16px">
        <x-admin.stat label="E-mails journalisés" :value="$totals['all']" icon="file-text" tone="blue" />
        <x-admin.stat label="Envoyés" :value="$totals['sent']" icon="check" tone="green" />
        <x-admin.stat label="En échec" :value="$totals['failed']" icon="flag" tone="red" />
    </div>

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.mail.logs') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Rechercher une adresse ou un nom…">
                </div>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts</option>
                    @foreach(['sent' => 'Envoyés', 'failed' => 'En échec', 'pending' => 'En cours'] as $code => $label)
                        <option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="template" aria-label="Modèle">
                    <option value="">Tous les modèles</option>
                    @foreach($templates as $code => $label)
                        <option value="{{ $code }}" @selected($filters['template'] === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($filters['email'] !== '')
                    <input type="hidden" name="email" value="{{ $filters['email'] }}">
                @endif
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($filters['q'] !== '' || $filters['status'] !== '' || $filters['template'] !== '' || $filters['email'] !== '')
                    <a href="{{ route('admin.mail.logs') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($lines)
            <div class="card-body" style="border-bottom:1px solid var(--line);display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                <a href="{{ route('admin.mail.logs', array_filter(['q' => $filters['q'], 'status' => $filters['status'], 'template' => $filters['template']])) }}" class="btn btn-ghost btn-sm">&larr; Toutes les adresses</a>
                <strong>{{ $filters['email'] }}</strong>
                <span class="text-sm text-muted">{{ $lines->total() }} e-mail{{ $lines->total() > 1 ? 's' : '' }}</span>
            </div>

            @if($lines->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Objet</th>
                                <th class="hide-mobile">Modèle</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $line)
                                @php $status = $line->effectiveStatus(); @endphp
                                <tr>
                                    <td class="nowrap">
                                        <div class="cell-main">{{ $line->created_at?->format('d/m/Y') }}</div>
                                        <div class="cell-sub">{{ $line->created_at?->format('H:i:s') }}</div>
                                    </td>
                                    <td>
                                        <div class="cell-main">{{ $line->subject ?: '—' }}</div>
                                        @if($line->from_email)
                                            <div class="cell-sub">de {{ $line->from_email }}</div>
                                        @endif
                                        @if($status === 'failed' && $line->error)
                                            <div class="cell-sub" style="color:var(--danger)">{{ $line->error }}</div>
                                        @endif
                                    </td>
                                    <td class="hide-mobile text-sm">{{ $templates[$line->template] ?? ($line->template ?: 'Test / autre') }}</td>
                                    <td>
                                        <span class="badge {{ ['sent' => 'badge-success', 'failed' => 'badge-danger', 'pending' => 'badge-warning'][$status] }}">{{ \App\Models\MailLog::STATUSES[$status] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $lines->links('admin.partials.pagination') }}
            @else
                <x-admin.empty icon="file-text" title="Aucun e-mail" text="Aucun e-mail ne correspond à ces filtres pour cette adresse." />
            @endif
        @elseif($addresses->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Adresse</th>
                            <th>E-mails</th>
                            <th>Envoyés</th>
                            <th>Échecs</th>
                            <th class="hide-mobile">Dernier envoi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($addresses as $address)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.mail.logs', array_filter(['email' => $address->to_email, 'status' => $filters['status'], 'template' => $filters['template']])) }}" class="cell-main">{{ $address->to_email }}</a>
                                    @if($address->name)
                                        <div class="cell-sub">{{ $address->name }}</div>
                                    @endif
                                </td>
                                <td>{{ $address->total }}</td>
                                <td><span class="badge badge-success">{{ (int) $address->sent_count }}</span></td>
                                <td>
                                    @if((int) $address->failed_count > 0)
                                        <span class="badge badge-danger">{{ (int) $address->failed_count }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="hide-mobile text-sm text-muted nowrap">{{ \Illuminate\Support\Carbon::parse($address->last_at)->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $addresses->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="file-text" title="Aucun e-mail" text="Aucun e-mail n’a encore été envoyé, ou aucun ne correspond à ces filtres." />
        @endif
    </article>
@endsection
