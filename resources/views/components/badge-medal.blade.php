@props(['badge', 'size' => 'md', 'locked' => false])
@php
    $dimensions = match ($size) {
        'sm' => 'h-10 w-10 text-base',
        'lg' => 'h-20 w-20 text-3xl',
        default => 'h-14 w-14 text-xl',
    };
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full text-white shadow-soft ring-2 ring-white', $dimensions, $locked ? 'bg-slate-200 text-slate-400' : 'bg-gradient-to-br '.$badge->gradient()]) }} title="{{ $badge->name }} · {{ $badge->tierLabel() }}">
    <i class="{{ $badge->iconClass() }}" aria-hidden="true"></i>
</span>
