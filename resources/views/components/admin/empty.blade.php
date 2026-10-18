@props(['icon' => 'inbox', 'title' => 'Aucun résultat', 'text' => null])
<div class="empty">
    <span class="ico"><x-admin.icon :name="$icon" :size="26" /></span>
    <h3>{{ $title }}</h3>
    @if($text)
        <p>{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
