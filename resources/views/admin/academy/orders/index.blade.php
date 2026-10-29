@extends('admin.layouts.app')

@section('title', 'Commandes Academy')
@section('section', 'Tunivisions Academy')

@section('content')
    <x-admin.page-header title="Commandes et paiements" subtitle="Achats de cours et abonnements. Tant qu’aucun prestataire en ligne n’est configuré, validez ici le paiement reçu (virement, espèces…) : l’accès s’ouvre aussitôt pour le membre.">
        <x-slot:actions>
            <a href="{{ route('admin.academy.settings.edit') }}" class="btn btn-secondary"><x-admin.icon name="layers" :size="16" /> Réglages</a>
        </x-slot:actions>
    </x-admin.page-header>

    @error('order')<p class="error" style="margin-bottom:12px">{{ $message }}</p>@enderror

    <article class="card card-flush">
        <div class="toolbar">
            <form method="get" action="{{ route('admin.academy.orders.index') }}">
                <div class="search">
                    <x-admin.icon name="search" :size="16" />
                    <input type="search" name="q" value="{{ $term }}" placeholder="Référence, membre, cours…">
                </div>
                <select name="status" aria-label="Statut">
                    <option value="">Tous les statuts ({{ $pending }} en attente)</option>
                    @foreach(\App\Models\AcademyOrder::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">Filtrer</button>
                @if($term !== '' || $status !== '')
                    <a href="{{ route('admin.academy.orders.index') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
                @endif
            </form>
        </div>

        @if($orders->count())
            <div class="list">
                @foreach($orders as $order)
                    <div class="list-item" style="align-items:flex-start;flex-wrap:wrap">
                        <div class="grow" style="min-width:240px">
                            <strong style="display:inline">{{ $order->reference }}</strong>
                            <x-admin.badge :status="$order->status === 'paid' ? 'approved' : ($order->status === 'refunded' ? 'rejected' : $order->status)" :label="$order->statusLabel()" />
                            <div>{{ $order->label }} — {{ $order->amountLabel() }}</div>
                            <div class="text-sm text-muted">
                                {{ $order->user?->name ?? 'Membre supprimé' }} ({{ $order->user?->email }}) · {{ $order->created_at->translatedFormat('d M Y H:i') }}
                                @if($order->paid_at) · payée le {{ $order->paid_at->translatedFormat('d M Y') }} @endif
                                @if($order->provider_reference) · réf. {{ $order->provider_reference }} @endif
                            </div>
                        </div>
                        @if($order->status === 'pending')
                            <form method="post" action="{{ route('admin.academy.orders.pay', $order) }}" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                @csrf
                                <input type="text" name="provider_reference" maxlength="190" placeholder="Référence du paiement (facultatif)" aria-label="Référence du paiement">
                                <button type="submit" class="btn btn-primary btn-sm"><x-admin.icon name="check" :size="15" /> Valider le paiement</button>
                            </form>
                            <form method="post" action="{{ route('admin.academy.orders.cancel', $order) }}" onsubmit="return confirm('Annuler cette commande ?')">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">Annuler</button>
                            </form>
                        @elseif($order->status === 'paid')
                            <form method="post" action="{{ route('admin.academy.orders.refund', $order) }}" onsubmit="return confirm('Rembourser cette commande et retirer l’accès ?')">
                                @csrf
                                <button type="submit" class="btn btn-danger btn-sm">Rembourser</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            {{ $orders->links('admin.partials.pagination') }}
        @else
            <x-admin.empty icon="file-text" title="Aucune commande" text="Les commandes apparaissent ici dès qu’un membre achète un cours ou souscrit un abonnement." />
        @endif
    </article>
@endsection
