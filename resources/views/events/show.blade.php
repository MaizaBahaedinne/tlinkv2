@extends('layouts.base', ['title' => $event->title.' | T.Link'])

@section('content')
@php
    $money = fn ($amount) => rtrim(rtrim(number_format((float) $amount, 2, ',', ' '), '0'), ',').' TND';
    $btn = 'rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90';
    $btnGhost = 'rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50';
    $input = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-sm';
    $statusLabels = ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'waitlisted' => 'Liste d’attente', 'rejected' => 'Refusée', 'attended' => 'Présent(e)', 'absent' => 'Absent(e)'];
    $paymentLabels = ['free' => 'Gratuit', 'unpaid' => 'À régler', 'pending' => 'Paiement en cours de vérification', 'paid' => 'Payé'];
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container space-y-5 py-6">
        @if(session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                <ul class="list-disc pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            @if($event->coverUrl())
                <img src="{{ $event->coverUrl() }}" alt="" class="mb-5 h-48 w-full rounded-2xl object-cover md:h-64">
            @endif
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="font-display text-2xl font-semibold text-slate-900">{{ $event->title }}</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $event->starts_at->translatedFormat('l d F Y · H:i') }}
                        @if($event->ends_at) — {{ $event->ends_at->translatedFormat('d F Y · H:i') }}@endif
                    </p>
                    @if($event->location || $event->governorate)
                        <p class="text-sm text-slate-600">{{ collect([$event->location, $event->governorate?->name])->filter()->implode(' · ') }}</p>
                    @endif
                </div>
                @if($canManage)
                    <a href="{{ route('admin.events.overview', $event) }}" class="{{ $btnGhost }}">Gérer l’événement</a>
                @endif
            </div>
            @if($event->description)
                <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $event->description }}</p>
            @endif
        </section>

        {{-- Inscription / suivi --}}
        <section id="inscription" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-lg font-semibold text-slate-900">Inscription</h2>

            @if($registration)
                <div class="mt-3 space-y-3 text-sm">
                    <p>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $statusLabels[$registration->status] ?? $registration->status }}</span>
                        @if($registration->release)<span class="ml-2 text-slate-600">Release : <strong>{{ $registration->release->name }}</strong></span>@endif
                    </p>
                    @if((float) $registration->amount_due > 0)
                        <p>Montant dû : <strong>{{ $money($registration->amount_due) }}</strong> — {{ $paymentLabels[$registration->payment_status] ?? $registration->payment_status }}.
                            @if($outstanding > 0) Reste à régler : <strong>{{ $money($outstanding) }}</strong>.@endif
                        </p>
                    @endif

                    @if($registration->payments->isNotEmpty())
                        <ul class="space-y-1 text-xs text-slate-600">
                            @foreach($registration->payments as $payment)
                                <li>{{ $payment->created_at->format('d/m/Y') }} · {{ $payment->methodLabel() }} · {{ $money($payment->amount) }} · <strong>{{ $payment->statusLabel() }}</strong>@if($payment->rejection_reason) ({{ $payment->rejection_reason }})@endif</li>
                            @endforeach
                        </ul>
                    @endif

                    @if($outstanding > 0 && ! in_array($registration->status, ['waitlisted', 'rejected'], true))
                        <details class="rounded-2xl border border-slate-200 p-4" @if(session('error') || $errors->any()) open @endif>
                            <summary class="cursor-pointer font-semibold text-slate-900">Régler / envoyer une preuve de paiement</summary>
                            <div class="mt-3 space-y-2">
                                @foreach($enabledMethods as $code => $instructions)
                                    @continue($code === 'cash')
                                    @if($instructions !== '')
                                        <p class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700"><strong>{{ $methods[$code] }}</strong> : {{ $instructions }}</p>
                                    @endif
                                @endforeach
                                @if(isset($enabledMethods['cash']))
                                    <p class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700"><strong>Cash</strong> : à régler directement auprès de l’équipe d’organisation{{ $enabledMethods['cash'] !== '' ? ' — '.$enabledMethods['cash'] : '' }}.</p>
                                @endif
                            </div>
                            <form method="post" action="{{ route('member.events.pay', $event) }}" enctype="multipart/form-data" class="mt-3 grid gap-3 sm:grid-cols-2">
                                @csrf
                                <label class="text-xs font-semibold text-slate-600">Moyen de paiement
                                    <select name="method" class="{{ $input }} mt-1" required>
                                        @foreach($enabledMethods as $code => $instructions)
                                            @continue($code === 'cash')
                                            <option value="{{ $code }}" @selected(old('method') === $code)>{{ $methods[$code] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-600">Montant (TND)
                                    <input type="number" name="amount" min="0.01" step="0.01" max="{{ $outstanding }}" value="{{ old('amount', $outstanding) }}" class="{{ $input }} mt-1" required>
                                </label>
                                <label class="text-xs font-semibold text-slate-600">Référence (n° de transaction, reçu…)
                                    <input name="reference" maxlength="255" value="{{ old('reference') }}" class="{{ $input }} mt-1">
                                </label>
                                <label class="text-xs font-semibold text-slate-600">Preuve (image ou PDF)
                                    <input type="file" name="proof" accept="image/png,image/jpeg,image/webp,application/pdf" class="{{ $input }} mt-1" required>
                                </label>
                                <div class="sm:col-span-2"><button type="submit" class="{{ $btn }}">Envoyer le paiement</button></div>
                            </form>
                        </details>
                    @endif

                    <form method="post" action="{{ route('member.events.cancel', $event) }}" onsubmit="return confirm('Annuler votre inscription ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="{{ $btnGhost }}">Annuler mon inscription</button>
                    </form>
                </div>
            @elseif(! $registrationOpen)
                <p class="mt-3 text-sm text-slate-600">Les inscriptions ne sont pas ouvertes pour cet événement.</p>
            @elseif($releases->isNotEmpty())
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @foreach($releases as $release)
                        @php($remaining = $release->remaining())
                        <article class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-semibold text-slate-900">{{ $release->name }}</h3>
                                <span class="text-lg font-bold text-slate-900">{{ $release->priceLabel() }}</span>
                            </div>
                            @if($release->description)<p class="mt-1 text-sm text-slate-600">{{ $release->description }}</p>@endif
                            <p class="mt-1 text-xs text-slate-500">
                                @if($release->closes_at) Jusqu’au {{ $release->closes_at->format('d/m/Y H:i') }}.@endif
                                @if($remaining !== null) {{ $remaining }} place(s) restante(s).@endif
                            </p>
                            @if(! $release->isOpen())
                                <p class="mt-3 text-xs font-semibold text-slate-500">Pas encore ouverte ou clôturée.</p>
                            @elseif($remaining === 0)
                                <p class="mt-3 text-xs font-semibold text-rose-600">Épuisée.</p>
                            @else
                                <form method="post" action="{{ route('member.events.register', $event) }}" class="mt-3">
                                    @csrf
                                    <input type="hidden" name="release_id" value="{{ $release->id }}">
                                    <button type="submit" class="{{ $btn }}">Choisir cette release</button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <form method="post" action="{{ route('member.events.register', $event) }}" class="mt-3">
                    @csrf
                    <p class="mb-3 text-sm text-slate-600">{{ $event->priceLabel() ?? 'Participation' }}{{ $event->capacity ? ' · '.$event->capacity.' places' : '' }}</p>
                    <button type="submit" class="{{ $btn }}">M’inscrire</button>
                </form>
            @endif
        </section>

        @include('events._details', ['interactive' => true])
    </main>
</div>
@endsection
