@props(['title', 'subtitle' => null])
<div class="page-head">
    <div>
        <h1>{{ $title }}</h1>
        @if($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions) && ! $actions->isEmpty())
        <div class="actions">{{ $actions }}</div>
    @endif
</div>
