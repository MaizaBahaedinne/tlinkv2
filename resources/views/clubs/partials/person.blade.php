@php
    $subtitle ??= null;
    $badge ??= null;
    $initials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($person['name'], 0, 2));
    $tag = $person['url'] ? 'a' : 'div';
@endphp
<{{ $tag }} @if($person['url']) href="{{ $person['url'] }}" @endif class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 {{ $person['url'] ? 'transition hover:border-tlink-blue/40 hover:bg-slate-50' : '' }}">
    @if($person['photo'])
        <img src="{{ $person['photo'] }}" alt="" loading="lazy" class="h-10 w-10 shrink-0 rounded-full object-cover">
    @else
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-tlink-blue/10 text-xs font-bold text-tlink-blue">{{ $initials }}</span>
    @endif
    <span class="min-w-0">
        <span class="block truncate text-sm font-semibold text-slate-800">{{ $person['name'] }}</span>
        @if($subtitle)
            <span class="block truncate text-xs text-slate-500">{{ $subtitle }}</span>
        @endif
        @if($badge)
            <span class="mt-0.5 inline-block rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800">{{ $badge }}</span>
        @endif
    </span>
</{{ $tag }}>
