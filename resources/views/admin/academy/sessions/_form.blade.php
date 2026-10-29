@php
    $p = $prefix;
    $mine = old('_form') === $p;
    $val = fn (string $field, $default = null) => $mine ? old($field, $default) : $default;
    $err = fn (string $field): ?string => $mine ? $errors->first($field) : null;
    $local = fn ($date) => $date?->format('Y-m-d\TH:i');
@endphp
<input type="hidden" name="_form" value="{{ $p }}">
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-title">Titre</label>
    <input id="{{ $p }}-title" type="text" name="title" value="{{ $val('title', $session?->title) }}" maxlength="160" required>
    @if($err('title'))<p class="error">{{ $err('title') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-start">Début</label>
    <input id="{{ $p }}-start" type="datetime-local" name="starts_at" value="{{ $val('starts_at', $local($session?->starts_at)) }}" required>
    @if($err('starts_at'))<p class="error">{{ $err('starts_at') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-end">Fin</label>
    <input id="{{ $p }}-end" type="datetime-local" name="ends_at" value="{{ $val('ends_at', $local($session?->ends_at)) }}">
    @if($err('ends_at'))<p class="error">{{ $err('ends_at') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-course">Cours concerné</label>
    <select id="{{ $p }}-course" name="course_id">
        <option value="">Session ouverte à tous</option>
        @foreach($courses as $id => $title)
            <option value="{{ $id }}" @selected((int) $val('course_id', $session?->course_id) === $id)>{{ $title }}</option>
        @endforeach
    </select>
    @if($err('course_id'))<p class="error">{{ $err('course_id') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-location">Lieu</label>
    <input id="{{ $p }}-location" type="text" name="location" value="{{ $val('location', $session?->location) }}" maxlength="255" placeholder="En ligne, ou adresse">
    @if($err('location'))<p class="error">{{ $err('location') }}</p>@endif
</div>
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-url">Lien de connexion (réservé aux membres ayant accès au cours)</label>
    <input id="{{ $p }}-url" type="url" name="meeting_url" value="{{ $val('meeting_url', $session?->meeting_url) }}" maxlength="255" placeholder="https://">
    @if($err('meeting_url'))<p class="error">{{ $err('meeting_url') }}</p>@endif
</div>
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-description">Description</label>
    <textarea id="{{ $p }}-description" name="description" rows="3" maxlength="3000">{{ $val('description', $session?->description) }}</textarea>
    @if($err('description'))<p class="error">{{ $err('description') }}</p>@endif
</div>
<div>
    <label class="check"><input type="checkbox" name="is_published" value="1" @checked($val('is_published', $session?->is_published ?? true))> Publiée dans le calendrier</label>
</div>
