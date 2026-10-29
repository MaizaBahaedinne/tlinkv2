@extends('layouts.base', ['title' => 'Commande '.$order->reference.' | '.$name])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-2xl space-y-5 py-6">
        <a href="{{ route('academy.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $name }}</a>

        <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h1 class="font-display text-2xl font-semibold text-slate-900">Commande {{ $order->reference }}</h1>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Article</dt><dd class="font-medium text-slate-900">{{ $order->label }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Montant</dt><dd class="font-medium text-slate-900">{{ $order->amountLabel() }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Statut</dt><dd class="font-medium text-slate-900">{{ $order->statusLabel() }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase text-slate-500">Date</dt><dd class="font-medium text-slate-900">{{ $order->created_at->translatedFormat('d F Y') }}</dd></div>
            </dl>

            @if($order->status === 'pending')
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">Paiement à effectuer au nom de {{ $name }}</p>
                    @if($instructions !== '')
                        <p class="mt-1 whitespace-pre-line">{{ $instructions }}</p>
                    @else
                        <p class="mt-1">Les modalités de paiement vous seront communiquées par l’équipe.</p>
                    @endif
                    <p class="mt-2">Indiquez la référence <strong>{{ $order->reference }}</strong>. Votre accès s’ouvre dès que le paiement est validé par l’équipe.</p>
                    @if($contactEmail !== '')<p class="mt-1">Une question : <a class="font-semibold underline" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>@endif
                </div>
            @elseif($order->status === 'paid')
                <p class="rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-800">Paiement confirmé, merci ! Votre accès est ouvert.</p>
                @if($order->course)
                    <a href="{{ route('academy.courses.show', $order->course->slug) }}" class="inline-block rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Accéder au cours</a>
                @endif
            @else
                <p class="rounded-2xl bg-slate-100 p-4 text-sm text-slate-700">Cette commande est {{ mb_strtolower($order->statusLabel()) }}.</p>
            @endif
        </section>
    </main>
</div>
@endsection
