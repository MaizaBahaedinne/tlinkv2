@php
    $map = [
        'free' => ['Gratuit', 'neutral'],
        'unpaid' => ['Non payé', 'danger'],
        'pending' => ['À valider', 'warning'],
        'paid' => ['Payé', 'success'],
    ];
    [$paymentLabel, $paymentTone] = $map[$status] ?? [$status, 'neutral'];
@endphp
<span class="badge badge-{{ $paymentTone }}">{{ $paymentLabel }}</span>
