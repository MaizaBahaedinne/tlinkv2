@php
    $p = $prefix;
    $mine = old('_form') === $p;
    $val = fn (string $field, $default = null) => $mine ? old($field, $default) : $default;
    $err = fn (string $field): ?string => $mine ? $errors->first($field) : null;
@endphp
<input type="hidden" name="_form" value="{{ $p }}">
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-title">Titre du module</label>
    <input id="{{ $p }}-title" type="text" name="title" value="{{ $val('title', $module?->title) }}" maxlength="160" required>
    @if($err('title'))<p class="error">{{ $err('title') }}</p>@endif
</div>
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-description">Description</label>
    <textarea id="{{ $p }}-description" name="description" rows="2" maxlength="2000">{{ $val('description', $module?->description) }}</textarea>
    @if($err('description'))<p class="error">{{ $err('description') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-badge">Badge de fin de module</label>
    <select id="{{ $p }}-badge" name="badge_id">
        <option value="">Aucun</option>
        @foreach($badges as $id => $name)
            <option value="{{ $id }}" @selected((int) $val('badge_id', $module?->badge_id) === $id)>{{ $name }}</option>
        @endforeach
    </select>
    @if($err('badge_id'))<p class="error">{{ $err('badge_id') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-sort">Ordre</label>
    <input id="{{ $p }}-sort" type="number" name="sort_order" value="{{ $val('sort_order', $module?->sort_order) }}" min="0" max="100000" placeholder="Automatique">
    @if($err('sort_order'))<p class="error">{{ $err('sort_order') }}</p>@endif
</div>
