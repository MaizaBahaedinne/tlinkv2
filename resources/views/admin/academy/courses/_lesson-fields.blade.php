@php
    $p = $prefix;
    $mine = old('_form') === $p;
    $val = fn (string $field, $default = null) => $mine ? old($field, $default) : $default;
    $err = fn (string $field): ?string => $mine ? $errors->first($field) : null;
@endphp
<input type="hidden" name="_form" value="{{ $p }}">
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-title">Titre de la leçon</label>
    <input id="{{ $p }}-title" type="text" name="title" value="{{ $val('title', $lesson?->title) }}" maxlength="160" required>
    @if($err('title'))<p class="error">{{ $err('title') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-kind">Type</label>
    <select id="{{ $p }}-kind" name="kind" required>
        @foreach(\App\Models\AcademyLesson::KINDS as $value => $label)
            <option value="{{ $value }}" @selected($val('kind', $lesson?->kind ?? 'text') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @if($err('kind'))<p class="error">{{ $err('kind') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-video">Lien vidéo (YouTube ou Vimeo)</label>
    <input id="{{ $p }}-video" type="url" name="video_url" value="{{ $val('video_url', $lesson?->video_url) }}" maxlength="255" placeholder="https://">
    @if($err('video_url'))<p class="error">{{ $err('video_url') }}</p>@endif
</div>
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-content">Contenu / consignes</label>
    <textarea id="{{ $p }}-content" name="content" rows="5" maxlength="20000">{{ $val('content', $lesson?->content) }}</textarea>
    @if($err('content'))<p class="error">{{ $err('content') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-duration">Durée (minutes)</label>
    <input id="{{ $p }}-duration" type="number" name="duration_minutes" value="{{ $val('duration_minutes', $lesson?->duration_minutes) }}" min="0" max="1000">
    @if($err('duration_minutes'))<p class="error">{{ $err('duration_minutes') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-points">Points à la validation</label>
    <input id="{{ $p }}-points" type="number" name="points" value="{{ $val('points', $lesson?->points ?? 10) }}" min="0" max="1000">
    @if($err('points'))<p class="error">{{ $err('points') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-sort">Ordre</label>
    <input id="{{ $p }}-sort" type="number" name="sort_order" value="{{ $val('sort_order', $lesson?->sort_order) }}" min="0" max="100000" placeholder="Automatique">
    @if($err('sort_order'))<p class="error">{{ $err('sort_order') }}</p>@endif
</div>
<div style="align-self:end">
    <label class="check"><input type="checkbox" name="is_preview" value="1" @checked($val('is_preview', $lesson?->is_preview ?? false))> Aperçu gratuit</label>
</div>
