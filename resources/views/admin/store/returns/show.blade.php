@extends('admin.layouts.app')

@section('title', 'Retour '.$return->reference)
@section('section', 'Tunivisions Stores')

@php
    $suggested = $return->items->sum(fn ($line) => $line->quantity * (float) $line->orderItem->unit_price);
@endphp

@section('content')
    <x-admin.page-header :title="'Retour '.$return->reference" :subtitle="'Commande '.$return->order?->reference.' · demandé le '.$return->created_at->translatedFormat('d F Y')">
        <x-slot:actions>
            <a href="{{ route('admin.store.returns.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
            @if($return->order)<a href="{{ route('admin.store.orders.show', $return->order) }}" class="btn btn-secondary">Voir la commande</a>@endif
        </x-slot:actions>
    </x-admin.page-header>

    @error('return')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

    <div class="grid-3-1">
        <article class="card card-flush">
            <div class="card-head"><h2 class="card-title">Articles à retourner</h2></div>
            <div class="list">
                @foreach($return->items as $line)
                    <div class="list-item">
                        <div class="grow">
                            <strong style="display:inline">{{ $line->orderItem->name }}</strong>
                            @if($line->orderItem->variant_label)<span class="text-sm text-muted">· {{ $line->orderItem->variant_label }}</span>@endif
                            @if($line->orderItem->kind === 'pack')<x-admin.badge status="info" label="Pack" />@endif
                        </div>
                        <span>× {{ $line->quantity }}</span>
                        <span class="text-sm text-muted">{{ \App\Support\Store\StoreSettings::money($line->quantity * (float) $line->orderItem->unit_price) }}</span>
                    </div>
                @endforeach
            </div>
            <div style="padding:12px 16px">
                <p class="text-sm"><strong>Motif :</strong> {{ $return->reason }}</p>
                @if($return->details)<p class="text-sm">{{ $return->details }}</p>@endif
                <p class="text-sm"><strong>Souhait :</strong> {{ $return->resolutionLabel() }}</p>
                @if($return->admin_note)<p class="text-sm"><strong>Note :</strong> {{ $return->admin_note }}</p>@endif
            </div>
        </article>

        <article class="card">
            <h2 class="card-title">Traitement</h2>
            <p><x-admin.badge :status="$return->status === 'completed' ? 'approved' : $return->status" :label="$return->statusLabel()" /></p>

            @if($return->status === 'requested')
                <form method="post" action="{{ route('admin.store.returns.decide', $return) }}" class="form-grid" style="grid-template-columns:1fr">
                    @csrf
                    <x-admin.textarea name="admin_note" label="Note pour le membre (en cas de refus : le motif)" rows="3" maxlength="2000" />
                    <div class="inline-form">
                        <button type="submit" name="decision" value="approved" class="btn btn-primary btn-sm">Accepter</button>
                        <button type="submit" name="decision" value="rejected" class="btn btn-danger btn-sm">Refuser</button>
                    </div>
                </form>
            @elseif($return->status === 'approved')
                <form method="post" action="{{ route('admin.store.returns.receive', $return) }}" class="form-grid" style="grid-template-columns:1fr">
                    @csrf
                    <label style="font-weight:400"><input type="checkbox" name="restock" value="1" checked> Remettre les articles en stock</label>
                    <div><button type="submit" class="btn btn-primary btn-sm">Marquer comme reçu</button></div>
                </form>
            @elseif($return->status === 'received')
                <form method="post" action="{{ route('admin.store.returns.complete', $return) }}" class="form-grid" style="grid-template-columns:1fr">
                    @csrf
                    @if($return->resolution === 'refund')
                        <x-admin.input name="refund_amount" label="Montant remboursé (TND)" type="number" step="0.01" min="0" :value="$suggested" hint="Plafonné à ce qui a été payé. Sans paiement enregistré, aucun remboursement n’est inscrit." />
                    @else
                        <p class="hint">Échange : préparez le remplacement manuellement (nouvelle commande ou envoi direct).</p>
                    @endif
                    <x-admin.textarea name="admin_note" label="Note (facultatif)" :value="$return->admin_note" rows="2" maxlength="2000" />
                    <div><button type="submit" class="btn btn-primary btn-sm">Clôturer le retour</button></div>
                </form>
            @elseif($return->status === 'completed')
                <p class="text-sm">Clôturé le {{ $return->completed_at?->translatedFormat('d M Y') }}@if((float) $return->refund_amount > 0) · remboursé {{ \App\Support\Store\StoreSettings::money((float) $return->refund_amount) }}@endif.</p>
            @endif
        </article>
    </div>
@endsection
