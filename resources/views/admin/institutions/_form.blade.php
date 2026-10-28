@php
    $i = $institution ?? null;
    $p = $prefix;
    // Après une erreur, seul le formulaire concerné retrouve sa saisie et son message.
    $mine = old('_form') === $p;
    $val = fn (string $field, $default = null) => $mine ? old($field, $default) : $default;
    $err = fn (string $field): ?string => $mine ? $errors->first($field) : null;
@endphp
<input type="hidden" name="_form" value="{{ $p }}">
<div>
    <label class="text-sm" for="{{ $p }}-name">Nom</label>
    <input id="{{ $p }}-name" type="text" name="name" value="{{ $val('name', $i?->name) }}" maxlength="160" required>
    @if($err('name'))<p class="error">{{ $err('name') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-type">Type</label>
    <select id="{{ $p }}-type" name="type" required>
        @foreach(\App\Models\Institution::TYPES as $value => $label)
            <option value="{{ $value }}" @selected($val('type', $i?->type ?? 'university') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @if($err('type'))<p class="error">{{ $err('type') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-parent">Université de rattachement (facultés)</label>
    <select id="{{ $p }}-parent" name="parent_id">
        <option value="">Aucune</option>
        @foreach($universities as $university)
            @continue($i && $university->id === $i->id)
            <option value="{{ $university->id }}" @selected((int) $val('parent_id', $i?->parent_id) === $university->id)>{{ $university->name }}</option>
        @endforeach
    </select>
    @if($err('parent_id'))<p class="error">{{ $err('parent_id') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-gov">Gouvernorat</label>
    <select id="{{ $p }}-gov" name="governorate_id">
        <option value="">Non précisé</option>
        @foreach($governorates as $governorate)
            <option value="{{ $governorate->id }}" @selected((int) $val('governorate_id', $i?->governorate_id) === $governorate->id)>{{ $governorate->name }}</option>
        @endforeach
    </select>
    @if($err('governorate_id'))<p class="error">{{ $err('governorate_id') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-address">Adresse</label>
    <input id="{{ $p }}-address" type="text" name="address" value="{{ $val('address', $i?->address) }}" maxlength="255">
    @if($err('address'))<p class="error">{{ $err('address') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-url">Site web</label>
    <input id="{{ $p }}-url" type="url" name="website_url" value="{{ $val('website_url', $i?->website_url) }}" maxlength="255" placeholder="https://">
    @if($err('website_url'))<p class="error">{{ $err('website_url') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-email">E-mail</label>
    <input id="{{ $p }}-email" type="email" name="email" value="{{ $val('email', $i?->email) }}" maxlength="255">
    @if($err('email'))<p class="error">{{ $err('email') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-phone">Téléphone</label>
    <input id="{{ $p }}-phone" type="text" name="phone" value="{{ $val('phone', $i?->phone) }}" maxlength="40">
    @if($err('phone'))<p class="error">{{ $err('phone') }}</p>@endif
</div>
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-desc">Description</label>
    <textarea id="{{ $p }}-desc" name="description" rows="3" maxlength="2000" style="height:auto;padding:10px 12px">{{ $val('description', $i?->description) }}</textarea>
    @if($err('description'))<p class="error">{{ $err('description') }}</p>@endif
</div>
<div style="grid-column:1/-1">
    <x-admin.logo-field :current="$i?->logoUrl()" :name="$i?->name ?? ''" :form="$p" />
</div>
<label class="check" style="grid-column:1/-1">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked($i ? $i->is_active : true)>
    <span class="text-sm">Actif (proposé dans les fiches de clubs et les profils)</span>
</label>
