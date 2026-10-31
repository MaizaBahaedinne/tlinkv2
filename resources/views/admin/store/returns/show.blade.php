@extends('admin.layouts.app')

@section('title', 'Retour '.$return->reference)
@section('section', 'Tunivisions Stores')

@php
    $suggested = $return->items->sum(fn ($line) => $line->quantity * (float) $line->orderItem->unit_price);
@endphp

@section('content')
    <x-admin.page-header :title="'Retour '.$return->reference" :subtitle="'Commande '.$return->order?->reference.' · demandé le '.$return->created_at->translatedFormat('d F Y')">
        <x-slot:actions>
            <a href="{{ route('admin.store.returns.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retours</a>
            @if($return->order)<a href="{{ route('admin.store.orders.show', $return->order) }}" class="btn btn-secondary"><x-admin.icon name="shopping-bag" :size="16" /> Voir la commande</a>@endif
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.store._tabs')

    <div class="grid-3-1">
        <div class="store-aside">
            <article class="card card-flush">
                <div class="card-head"><h2>Articles à retourner</h2></div>
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Article</th><th class="num">Qté</th><th class="num">Valeur</th></tr></thead>
                        <tbody>
                            @foreach($return->items as $line)
                                <tr>
                                    <td>
                                        <span class="cell-main">{{ $line->orderItem->name }}</span>
                                        @if($line->orderItem->kind === 'pack')<span class="badge badge-info">Pack</span>@endif
                                        @if($line->orderItem->variant_label)<div class="cell-sub">{{ $line->orderItem->variant_label }}</div>@endif
                                    </td>
                                    <td class="num">{{ $line->quantity }}</td>
                                    <td class="num">{{ \App\Support\Store\StoreSettings::money($line->quantity * (float) $line->orderItem->unit_price) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card">
                <div class="card-head"><h2>Demande du membre</h2></div>
                <div class="card-body">
                    <dl class="uinfo">
                        <div><dt>Membre</dt><dd>{{ $return->user?->name ?? 'Compte supprimé' }}</dd></div>
                        <div><dt>Motif</dt><dd>{{ $return->reason }}</dd></div>
                        @if($return->details)<div><dt>Détails</dt><dd class="ubio">{{ $return->details }}</dd></div>@endif
                        <div><dt>Souhait</dt><dd>{{ $return->resolutionLabel() }}</dd></div>
                        @if($return->admin_note)<div><dt>Réponse</dt><dd class="ubio">{{ $return->admin_note }}</dd></div>@endif
                    </dl>
                </div>
            </article>
        </div>

        <article class="card">
            <div class="card-head">
                <h2>Traitement</h2>
                <span class="badge badge-{{ $return->statusTone() }}">{{ $return->statusLabel() }}</span>
            </div>

            @if($return->status === 'requested')
                <form method="post" action="{{ route('admin.store.returns.decide', $return) }}" class="card-body stack">
                    @csrf
                    <x-admin.textarea name="admin_note" label="Note pour le membre" rows="3" maxlength="2000" hint="En cas de refus, indiquez le motif." />
                    <div class="inline-form">
                        <button type="submit" name="decision" value="approved" class="btn btn-primary btn-sm"><x-admin.icon name="check" :size="15" /> Accepter</button>
                        <button type="submit" name="decision" value="rejected" class="btn btn-danger btn-sm"><x-admin.icon name="x" :size="15" /> Refuser</button>
                    </div>
                </form>
            @elseif($return->status === 'approved')
                <form method="post" action="{{ route('admin.store.returns.receive', $return) }}" class="card-body stack">
                    @csrf
                    <p class="text-sm text-muted">Retour accepté. Quand les articles sont revenus à la boutique, marquez-les comme reçus.</p>
                    <label class="check check-card"><input type="checkbox" name="restock" value="1" checked><span>Remettre les articles en stock<small>Décochez s’ils sont abîmés ou inutilisables.</small></span></label>
                    <div><button type="submit" class="btn btn-primary btn-sm">Marquer comme reçu</button></div>
                </form>
            @elseif($return->status === 'received')
                <form method="post" action="{{ route('admin.store.returns.complete', $return) }}" class="card-body stack">
                    @csrf
                    @if($return->resolution === 'refund')
                        <x-admin.input name="refund_amount" label="Montant remboursé (TND)" type="number" step="0.01" min="0" :value="$suggested" hint="Plafonné à ce qui a été payé. Sans paiement enregistré, aucun remboursement n’est inscrit." />
                    @else
                        <div class="callout"><x-admin.icon name="package" :size="18" /> Échange : préparez le remplacement à la main (nouvel envoi ou nouvelle commande).</div>
                    @endif
                    <x-admin.textarea name="admin_note" label="Note (facultatif)" :value="$return->admin_note" rows="2" maxlength="2000" />
                    <div><button type="submit" class="btn btn-primary btn-sm">Clôturer le retour</button></div>
                </form>
            @elseif($return->status === 'completed')
                <div class="card-body">
                    <p class="text-sm">Clôturé le {{ $return->completed_at?->translatedFormat('d F Y') }}@if((float) $return->refund_amount > 0) · remboursé {{ \App\Support\Store\StoreSettings::money((float) $return->refund_amount) }}@endif.</p>
                </div>
            @else
                <div class="card-body"><p class="text-sm text-muted">Demande refusée : aucune action n’est attendue.</p></div>
            @endif
        </article>
    </div>
@endsection
