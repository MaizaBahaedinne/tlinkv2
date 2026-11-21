@extends('admin.layouts.app')

@section('title', 'Paiements — '.$event->title)
@section('section', 'Événements')

@php
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ').' TND';
    // Valeur d'un champ : l'ancienne saisie uniquement pour la modale qui vient d'échouer.
    $o = fn (string $form, string $key, $default = null) => old('_form') === $form ? old($key, $default) : $default;
    $canRecord = in_array('payments_record', $abilities, true);
    $canValidate = in_array('payments_validate', $abilities, true);
    $canSettings = in_array('payments_settings', $abilities, true);
    $pending = $payments->where('status', 'pending')->values();
    $payable = $balances->filter(fn ($row) => $row['outstanding'] > 0)->values();
    $tones = ['pending' => 'warning', 'validated' => 'success', 'rejected' => 'danger'];
    // Un validateur ne peut pas valider son propre paiement.
    $decidable = fn ($payment) => $canValidate && $payment->registration?->user_id !== auth()->id();
@endphp

@section('content')
    @include('admin.events.manage._header')

    <div class="stats" style="margin-bottom:16px">
        <x-admin.stat label="Encaissé (validé)" :value="$money($stats['collected'])" icon="check" tone="green" />
        <x-admin.stat label="En attente de validation" :value="$money($stats['pending'])" icon="activity" tone="amber" :hint="$stats['pendingCount'].' paiement(s)'" />
        <x-admin.stat label="Reste à encaisser" :value="$money($stats['toCollect'])" icon="file-text" tone="blue" :hint="$balances->count().' inscription(s) avec un solde'" />
    </div>

    <div class="tabs" role="tablist" aria-label="Sections des paiements" data-tabset style="margin-bottom:16px">
        <button type="button" role="tab" class="tab is-active" id="tab-btn-pending" aria-controls="pane-pending" aria-selected="true">À valider <span class="n">{{ $pending->count() }}</span></button>
        <button type="button" role="tab" class="tab" id="tab-btn-all" aria-controls="pane-all" aria-selected="false">Tous les paiements <span class="n">{{ $payments->count() }}</span></button>
        <button type="button" role="tab" class="tab" id="tab-btn-balances" aria-controls="pane-balances" aria-selected="false">Soldes à régler <span class="n">{{ $balances->count() }}</span></button>
        @if($canSettings)
            <button type="button" role="tab" class="tab" id="tab-btn-methods" aria-controls="pane-methods" aria-selected="false">Moyens de paiement</button>
        @endif
    </div>

    {{-- ===================== À valider ===================== --}}
    <section class="tab-panel" id="pane-pending" role="tabpanel" aria-labelledby="tab-btn-pending">
        <div class="tr-toolbar">
            <p class="text-sm text-muted">Les preuves sont validées ou rejetées par un validateur, jamais par le payeur lui-même.</p>
            @if($canRecord)
                <button type="button" class="btn btn-primary" data-modal-open="payment-record"><x-admin.icon name="plus" :size="16" /> Enregistrer un paiement</button>
            @endif
        </div>

        <article class="card card-flush">
            @if($pending->isEmpty())
                <x-admin.empty icon="check" title="Rien à valider" text="Les paiements déposés par les participants ou enregistrés par l’équipe apparaissent ici tant qu’ils attendent une décision." />
            @else
                <div class="list">
                    @foreach($pending as $payment)
                        <div class="list-item" style="flex-wrap:wrap">
                            <div class="grow" style="min-width:220px">
                                <strong>{{ $payment->registration?->user?->name ?? '—' }}</strong>
                                <div class="text-sm text-muted">{{ $payment->methodLabel() }}{{ $payment->reference ? ' · réf. '.$payment->reference : '' }} · déposé par {{ $payment->submitter?->name ?? '—' }} le {{ $payment->created_at?->format('d/m/Y H:i') }}</div>
                                @if($payment->note)<div class="text-sm text-muted">{{ $payment->note }}</div>@endif
                            </div>
                            <strong class="nowrap">{{ $money($payment->amount) }}</strong>
                            <div class="inline-form" style="justify-content:flex-end">
                                @if($payment->hasProof())
                                    <a href="{{ route('admin.events.payments.proof', [$event, $payment]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-admin.icon name="file-text" :size="14" /> Preuve</a>
                                @else
                                    <span class="text-sm text-muted">Sans preuve</span>
                                @endif
                                @if($decidable($payment))
                                    <form method="post" action="{{ route('admin.events.payments.validate', [$event, $payment]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm"><x-admin.icon name="check" :size="14" /> Valider</button>
                                    </form>
                                    <button type="button" class="btn btn-danger btn-sm" data-modal-open="payment-reject-{{ $payment->id }}">Rejeter</button>
                                @elseif($canValidate)
                                    <span class="text-sm text-muted">Votre propre paiement : un autre validateur doit décider.</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>

    {{-- ===================== Tous les paiements ===================== --}}
    <section class="tab-panel" id="pane-all" role="tabpanel" aria-labelledby="tab-btn-all" hidden>
        <article class="card card-flush">
            @if($payments->isEmpty())
                <x-admin.empty icon="check" title="Aucun paiement" text="Aucun paiement n’a encore été déposé ou enregistré." />
            @else
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Participant</th><th>Moyen</th><th>Montant</th><th class="hide-mobile">Référence</th><th>Statut</th><th></th></tr></thead>
                        <tbody>
                            @foreach($payments as $payment)
                                <tr>
                                    <td>
                                        <div class="cell-main">{{ $payment->registration?->user?->name ?? '—' }}</div>
                                        <div class="cell-sub">{{ $payment->created_at?->format('d/m/Y H:i') }} · par {{ $payment->submitter?->name ?? '—' }}</div>
                                    </td>
                                    <td>{{ $payment->methodLabel() }}</td>
                                    <td class="nowrap">{{ $money($payment->amount) }}</td>
                                    <td class="hide-mobile text-sm">{{ $payment->reference ?: '—' }}@if($payment->note)<div class="cell-sub">{{ $payment->note }}</div>@endif</td>
                                    <td>
                                        <span class="badge badge-{{ $tones[$payment->status] ?? 'neutral' }}">{{ $payment->statusLabel() }}</span>
                                        @if($payment->status !== 'pending' && $payment->validator)<div class="cell-sub">par {{ $payment->validator->name }}</div>@endif
                                        @if($payment->rejection_reason)<div class="cell-sub">{{ $payment->rejection_reason }}</div>@endif
                                    </td>
                                    <td class="text-right nowrap">
                                        @if($payment->hasProof())<a href="{{ route('admin.events.payments.proof', [$event, $payment]) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">Preuve</a>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </article>
    </section>

    {{-- ===================== Soldes ===================== --}}
    <section class="tab-panel" id="pane-balances" role="tabpanel" aria-labelledby="tab-btn-balances" hidden>
        <article class="card card-flush">
            @if($balances->isEmpty())
                <x-admin.empty icon="check" title="Aucun solde à régler" text="Toutes les inscriptions payantes sont entièrement réglées." />
            @else
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Participant</th><th>Dû</th><th class="hide-mobile">Validé</th><th class="hide-mobile">En attente</th><th>Reste</th><th></th></tr></thead>
                        <tbody>
                            @foreach($balances as $row)
                                <tr>
                                    <td><div class="cell-main">{{ $row['registration']->user?->name }}</div><div class="cell-sub">{{ $row['registration']->user?->email }}</div></td>
                                    <td class="nowrap">{{ $money($row['due']) }}</td>
                                    <td class="hide-mobile nowrap">{{ $money($row['validated']) }}</td>
                                    <td class="hide-mobile nowrap">{{ $money($row['pending']) }}</td>
                                    <td class="nowrap"><strong>{{ $money($row['remaining']) }}</strong></td>
                                    <td class="text-right">
                                        @if($canRecord && $row['outstanding'] > 0)
                                            <button type="button" class="btn btn-secondary btn-sm" data-modal-open="payment-record" data-payment-for="{{ $row['registration']->id }}" data-payment-amount="{{ number_format($row['outstanding'], 2, '.', '') }}">Enregistrer un paiement</button>
                                        @elseif($row['pending'] > 0)
                                            <span class="text-sm text-muted">Paiement à valider</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </article>
    </section>

    {{-- ===================== Moyens de paiement ===================== --}}
    @if($canSettings)
        <section class="tab-panel" id="pane-methods" role="tabpanel" aria-labelledby="tab-btn-methods" hidden>
            <form method="post" action="{{ route('admin.events.payments.methods', $event) }}" class="card card-flush">
                @csrf @method('PUT')
                <div class="card-head">
                    <div>
                        <h2>Moyens de paiement acceptés</h2>
                        <div class="sub">Les instructions (RIB, numéro D17, adresse de versement…) sont affichées aux participants.</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
                </div>
                <div class="card-body check-stack">
                    @foreach($methods as $code => $label)
                        <div class="check-card" style="display:block">
                            <input type="hidden" name="methods[{{ $code }}][enabled]" value="0">
                            <label class="check"><input type="checkbox" name="methods[{{ $code }}][enabled]" value="1" @checked(isset($enabled[$code]))> <strong>{{ $label }}</strong></label>
                            <textarea name="methods[{{ $code }}][instructions]" rows="2" maxlength="1000" style="margin-top:8px" placeholder="Instructions pour ce moyen de paiement">{{ $config[$code]['instructions'] ?? '' }}</textarea>
                        </div>
                    @endforeach
                </div>
            </form>
        </section>
    @endif

    {{-- ===================== Modales ===================== --}}
    @if($canRecord)
        <x-admin.modal id="payment-record" title="Enregistrer un paiement" :action="route('admin.events.payments.store', $event)" submit="Enregistrer" icon="check" :files="true">
            <div class="field" style="grid-column:1/-1">
                <label>Participant <span class="req">*</span></label>
                <select name="registration_id" id="payment-registration" required>
                    <option value="">— choisir —</option>
                    @foreach($payable as $row)
                        <option value="{{ $row['registration']->id }}" data-outstanding="{{ number_format($row['outstanding'], 2, '.', '') }}" @selected((string) $o('payment-record', 'registration_id') === (string) $row['registration']->id)>{{ $row['registration']->user?->name }} — reste {{ $money($row['outstanding']) }}</option>
                    @endforeach
                </select>
                @if($payable->isEmpty())<p class="hint">Aucune inscription n’a de solde à enregistrer pour le moment.</p>@endif
            </div>
            <div class="field">
                <label>Moyen <span class="req">*</span></label>
                <select name="method" required>
                    @foreach($methods as $code => $label)<option value="{{ $code }}" @selected($o('payment-record', 'method') === $code)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label>Montant (TND) <span class="req">*</span></label><input type="number" name="amount" id="payment-amount" min="0.01" step="0.01" required value="{{ $o('payment-record', 'amount') }}"></div>
            <div class="field" style="grid-column:1/-1"><label>Référence (n° de reçu, virement…)</label><input name="reference" maxlength="255" value="{{ $o('payment-record', 'reference') }}"></div>
            <div class="field" style="grid-column:1/-1"><label>Preuve (image ou PDF)</label><input type="file" name="proof" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
            <div class="field" style="grid-column:1/-1"><label>Note</label><input name="note" maxlength="255" value="{{ $o('payment-record', 'note') }}"></div>
        </x-admin.modal>
    @endif

    @foreach($pending as $payment)
        @if($decidable($payment))
            <x-admin.modal :id="'payment-reject-'.$payment->id" :title="'Rejeter le paiement de '.($payment->registration?->user?->name ?? '—')" :action="route('admin.events.payments.reject', [$event, $payment])" submit="Rejeter le paiement">
                <p class="text-sm text-muted" style="grid-column:1/-1">{{ $payment->methodLabel() }} · {{ $money($payment->amount) }}. Le participant sera prévenu avec le motif.</p>
                <div class="field" style="grid-column:1/-1"><label>Motif du rejet <span class="req">*</span></label><input name="reason" required maxlength="255" value="{{ $o('payment-reject-'.$payment->id, 'reason') }}" placeholder="Preuve illisible, montant incorrect…"></div>
            </x-admin.modal>
        @endif
    @endforeach
@endsection

@push('scripts')
<script>
    // Depuis la liste des soldes : préremplit le participant et le montant restant dans la modale d'enregistrement.
    document.querySelectorAll('[data-payment-for]').forEach(function (button) {
        button.addEventListener('click', function () {
            var select = document.getElementById('payment-registration');
            var amount = document.getElementById('payment-amount');
            if (!select || !amount) { return; }
            select.value = button.getAttribute('data-payment-for');
            amount.value = button.getAttribute('data-payment-amount');
        });
    });
    var registration = document.getElementById('payment-registration');
    if (registration) {
        registration.addEventListener('change', function () {
            var option = registration.options[registration.selectedIndex];
            var amount = document.getElementById('payment-amount');
            if (option && option.dataset.outstanding && amount && !amount.value) { amount.value = option.dataset.outstanding; }
        });
    }
</script>
@endpush
