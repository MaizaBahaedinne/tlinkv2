@props(['url' => null, 'name' => '', 'icon' => 'fa-solid fa-building', 'size' => 'md'])
@php
    $dimensions = $size === 'sm' ? 'h-16 w-16 text-xl' : 'h-24 w-24 text-3xl';
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-400', $dimensions]) }}>
    @if($url)
        <img src="{{ $url }}" alt="{{ $name }}" loading="lazy" class="h-full w-full object-contain p-1">
    @else
        <i class="{{ $icon }}" aria-hidden="true"></i>
    @endif
</span>
