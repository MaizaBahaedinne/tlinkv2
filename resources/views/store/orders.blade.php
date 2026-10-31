@extends('layouts.base', ['title' => 'Mes commandes | '.$settings['name']])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-5 py-6">
        <a href="{{ route('store.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $settings['name'] }}</a>
        <h1 class="font-display text-2xl font-semibold text-slate-900">Mes commandes</h1>

        @include('store._flash')

        @if($orders->isEmpty())
            <p class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Vous n’avez pas encore passé de commande.</p>
        @else
            <section class="divide-y divide-slate-100 rounded-3xl border border-slate-200 bg-white shadow-soft">
                @foreach($orders as $order)
                    <a href="{{ route('store.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-slate-50">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $order->reference }}</p>
                            <p class="text-xs text-slate-500">{{ $order->created_at->translatedFormat('d F Y') }} · {{ $order->paymentStatusLabel() }}@if($order->returns->isNotEmpty()) · {{ $order->returns->count() }} retour(s)@endif</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-slate-900">{{ $order->totalLabel() }}</p>
                            <p class="text-xs font-semibold text-tlink-blue">{{ $order->statusLabel() }}</p>
                        </div>
                    </a>
                @endforeach
            </section>
            <div>{{ $orders->links() }}</div>
        @endif
    </main>
</div>
@endsection
