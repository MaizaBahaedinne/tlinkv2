@extends('admin.layouts.app')

@section('title', 'Paiements — '.$event->title)
@section('section', 'Événements')

@section('content')
    @include('admin.events.manage._header')

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Paiements</h2>
                <div class="sub">Les preuves sont validées ou rejetées par un validateur, jamais par le payeur lui-même.</div>
            </div>
        </div>

        @if($payments->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Participant</th><th>Moyen</th><th>Montant</th><th class="hide-mobile">Référence</th><th>Statut</th><th>Actions</th></tr></thead>
                    <tbody>
                        @foreach($payments as $payment)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $payment->registration?->user?->name ?? '—' }}</div>
                                    <div class="cell-sub">Déposé par {{ $payment->submitter?->name ?? '—' }} · {{ $payment->created_at?->format('d/m/Y H:i') }}</div>
                                </td>
                                <td>{{ $payment->methodLabel() }}</td>
                                <td class="nowrap">{{ number_format((float) $payment->amount, 2, ',', ' ') }} TND</td>
                                <td class="hide-mobile text-sm">{{ $payment->reference ?: '—' }}@if($payment->note)<div class="cell-sub">{{ $payment->note }}</div>@endif</td>
                                <td>
                                    <x-admin.badge :status="$payment->status" :label="$payment->statusLabel()" />
                                    @if($payment->status !== 'pending' && $payment->validator)
                                        <div class="cell-sub">par {{ $payment->validator->name }}</div>
                                    @endif
                                    @if($payment->rejection_reason)<div class="cell-sub">{{ $payment->rejection_reason }}</div>@endif
                                </td>
                                <td class="nowrap">
                                    @if($payment->hasProof())
                                        <a href="{{ route('admin.events.payments.proof', [$event, $payment]) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">Preuve</a>
                                    @endif
                                    @if($payment->status === 'pending' && in_array('payments_validate', $abilities, true) && $payment->registration?->user_id !== auth()->id())
                                        <form method="post" action="{{ route('admin.events.payments.validate', [$event, $payment]) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">Valider</button>
                                        </form>
                                        <details style="display:inline-block">
                                            <summary class="btn btn-ghost btn-sm">Rejeter</summary>
                                            <form method="post" action="{{ route('admin.events.payments.reject', [$event, $payment]) }}" style="padding:8px 0">
                                                @csrf
                                                <input name="reason" required maxlength="255" placeholder="Motif du rejet">
                                                <button type="submit" class="btn btn-secondary btn-sm">Confirmer</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty icon="check" title="Aucun paiement" text="Les paiements déposés par les participants ou enregistrés par l’équipe apparaîtront ici." />
        @endif
    </article>

    @if(in_array('payments_record', $abilities, true))
        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head"><h2>Enregistrer un paiement</h2></div>
            <div class="card-body">
                @if($owing->count())
                    <form method="post" action="{{ route('admin.events.payments.store', $event) }}" class="form-grid" enctype="multipart/form-data">
                        @csrf
                        <div class="field">
                            <label>Participant <span class="req">*</span></label>
                            <select name="registration_id" required>
                                @foreach($owing as $registration)
                                    <option value="{{ $registration->id }}" @selected((string) old('registration_id') === (string) $registration->id)>{{ $registration->user?->name }} — dû {{ number_format((float) $registration->amount_due, 2, ',', ' ') }} TND</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label>Moyen <span class="req">*</span></label>
                            <select name="method" required>
                                @foreach($methods as $code => $label)<option value="{{ $code }}" @selected(old('method') === $code)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field"><label>Montant (TND) <span class="req">*</span></label><input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required></div>
                        <div class="field"><label>Référence (n° de reçu, virement…)</label><input name="reference" maxlength="255" value="{{ old('reference') }}"></div>
                        <div class="field"><label>Preuve (image ou PDF)</label><input type="file" name="proof" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
                        <div class="field"><label>Note</label><input name="note" maxlength="255" value="{{ old('note') }}"></div>
                        <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button></div>
                    </form>
                @else
                    <p class="text-muted">Aucune inscription avec un solde à régler.</p>
                @endif
            </div>
        </article>
    @endif

    @if(in_array('payments_settings', $abilities, true))
        <article class="card card-flush">
            <div class="card-head">
                <div>
                    <h2>Moyens de paiement acceptés</h2>
                    <div class="sub">Les instructions (RIB, numéro D17, adresse de versement…) sont affichées aux participants.</div>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.events.payments.methods', $event) }}" class="form-grid">
                    @csrf @method('PUT')
                    @foreach($methods as $code => $label)
                        <div class="field span-2">
                            <input type="hidden" name="methods[{{ $code }}][enabled]" value="0">
                            <label class="check"><input type="checkbox" name="methods[{{ $code }}][enabled]" value="1" @checked(isset($enabled[$code]))> <strong>{{ $label }}</strong></label>
                            <textarea name="methods[{{ $code }}][instructions]" rows="2" maxlength="1000" placeholder="Instructions pour ce moyen de paiement">{{ $config[$code]['instructions'] ?? '' }}</textarea>
                        </div>
                    @endforeach
                    <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button></div>
                </form>
            </div>
        </article>
    @endif
@endsection
