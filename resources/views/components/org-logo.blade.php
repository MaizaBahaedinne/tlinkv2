@props(['url' => null, 'name' => '', 'icon' => 'fa-solid fa-building', 'size' => 'md'])
@php
    $dimensions = $size === 'sm' ? 'h-9 w-9 text-sm' : 'h-12 w-12 text-lg';
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-400', $dimensions]) }}>
    @if($url)
        <img src="{{ $url }}" alt="{{ $name }}" loading="lazy" class="h-full w-full object-contain p-1">
    @else
        <i class="{{ $icon }}" aria-hidden="true"></i>
    @endif
</span>
