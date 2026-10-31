{{-- Étoiles en lecture seule : $value (0 à 5, décimales arrondies à la demi-étoile). --}}
@php
    $rounded = round(((float) $value) * 2) / 2;
@endphp
<span class="inline-flex items-center gap-0.5 {{ $size ?? 'text-sm' }}" role="img" aria-label="{{ number_format((float) $value, 1, ',', '') }} sur 5">
    @for($star = 1; $star <= 5; $star++)
        @if($rounded >= $star)
            <i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i>
        @elseif($rounded >= $star - 0.5)
            <i class="fa-solid fa-star-half-stroke text-amber-400" aria-hidden="true"></i>
        @else
            <i class="fa-regular fa-star text-slate-300" aria-hidden="true"></i>
        @endif
    @endfor
</span>
