@php
    $p = $prefix;
    $mine = old('_form') === $p;
    $val = fn (string $field, $default = null) => $mine ? old($field, $default) : $default;
    $err = fn (string $field): ?string => $mine ? $errors->first($field) : null;
@endphp
<input type="hidden" name="_form" value="{{ $p }}">
<div>
    <label class="text-sm" for="{{ $p }}-name">Nom</label>
    <input id="{{ $p }}-name" type="text" name="name" value="{{ $val('name', $plan?->name) }}" maxlength="120" required>
    @if($err('name'))<p class="error">{{ $err('name') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-price">Prix (TND)</label>
    <input id="{{ $p }}-price" type="number" step="0.01" min="0.01" max="99999" name="price" value="{{ $val('price', $plan?->price) }}" required>
    @if($err('price'))<p class="error">{{ $err('price') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-days">Durée (jours)</label>
    <input id="{{ $p }}-days" type="number" min="1" max="3650" name="duration_days" value="{{ $val('duration_days', $plan?->duration_days ?? 30) }}" required>
    @if($err('duration_days'))<p class="error">{{ $err('duration_days') }}</p>@endif
</div>
<div style="align-self:end">
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked($val('is_active', $plan?->is_active ?? true))> Proposé aux membres</label>
</div>
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-description">Description</label>
    <textarea id="{{ $p }}-description" name="description" rows="2" maxlength="1000">{{ $val('description', $plan?->description) }}</textarea>
    @if($err('description'))<p class="error">{{ $err('description') }}</p>@endif
</div>
