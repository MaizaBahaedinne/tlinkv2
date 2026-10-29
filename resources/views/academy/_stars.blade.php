@php
    $value = max(0, min(5, (float) $rating));
    $size = $size ?? 'text-sm';
@endphp
<span class="inline-flex items-center gap-0.5 {{ $size }}" role="img" aria-label="{{ number_format($value, 1, ',', '') }} sur 5">
    @for($i = 1; $i <= 5; $i++)
        <i class="fa-solid {{ $value >= $i - 0.25 ? 'fa-star text-amber-400' : ($value >= $i - 0.75 ? 'fa-star-half-stroke text-amber-400' : 'fa-star text-slate-300') }}" aria-hidden="true"></i>
    @endfor
</span>
