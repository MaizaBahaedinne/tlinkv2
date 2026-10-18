@props(['name' => '', 'size' => 40])
@php
    $initials = collect(preg_split('/\s+/', trim((string) $name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->join('');
@endphp
<span class="avatar" style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ max(11, (int) round($size * 0.38)) }}px;" aria-hidden="true">{{ $initials !== '' ? $initials : '?' }}</span>
