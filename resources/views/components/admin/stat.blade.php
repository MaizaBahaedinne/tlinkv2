@props(['label', 'value', 'icon' => 'activity', 'tone' => 'blue', 'hint' => null, 'href' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="stat">
    <span class="stat-icon tone-{{ $tone }}"><x-admin.icon :name="$icon" :size="22" /></span>
    <span>
        <span class="stat-label" style="display:block">{{ $label }}</span>
        <span class="stat-value" style="display:block">{{ $value }}</span>
        @if($hint)
            <span class="stat-hint" style="display:block">{{ $hint }}</span>
        @endif
    </span>
</{{ $tag }}>
