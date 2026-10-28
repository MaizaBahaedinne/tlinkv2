@php
    $i = $institution ?? null;
    $p = $prefix;
@endphp
<div>
    <label class="text-sm" for="{{ $p }}-name">Nom</label>
    <input id="{{ $p }}-name" type="text" name="name" value="{{ $i?->name ?? old('name') }}" maxlength="160" required>
    @error('name')<p class="error">{{ $message }}</p>@enderror
</div>
<div>
    <label class="text-sm" for="{{ $p }}-type">Type</label>
    <select id="{{ $p }}-type" name="type" required>
        @foreach(\App\Models\Institution::TYPES as $value => $label)
            <option value="{{ $value }}" @selected(($i?->type ?? old('type', 'university')) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('type')<p class="error">{{ $message }}</p>@enderror
</div>
<div>
    <label class="text-sm" for="{{ $p }}-parent">Université de rattachement (facultés)</label>
    <select id="{{ $p }}-parent" name="parent_id">
        <option value="">Aucune</option>
        @foreach($universities as $university)
            @continue($i && $university->id === $i->id)
            <option value="{{ $university->id }}" @selected((int) ($i?->parent_id ?? old('parent_id')) === $university->id)>{{ $university->name }}</option>
        @endforeach
    </select>
    @error('parent_id')<p class="error">{{ $message }}</p>@enderror
</div>
<div>
    <label class="text-sm" for="{{ $p }}-gov">Gouvernorat</label>
    <select id="{{ $p }}-gov" name="governorate_id">
        <option value="">Non précisé</option>
        @foreach($governorates as $governorate)
            <option value="{{ $governorate->id }}" @selected((int) ($i?->governorate_id ?? old('governorate_id')) === $governorate->id)>{{ $governorate->name }}</option>
        @endforeach
    </select>
    @error('governorate_id')<p class="error">{{ $message }}</p>@enderror
</div>
<div>
    <label class="text-sm" for="{{ $p }}-address">Adresse</label>
    <input id="{{ $p }}-address" type="text" name="address" value="{{ $i?->address ?? old('address') }}" maxlength="255">
    @error('address')<p class="error">{{ $message }}</p>@enderror
</div>
<div>
    <label class="text-sm" for="{{ $p }}-url">Site web</label>
    <input id="{{ $p }}-url" type="url" name="website_url" value="{{ $i?->website_url ?? old('website_url') }}" maxlength="255" placeholder="https://">
    @error('website_url')<p class="error">{{ $message }}</p>@enderror
</div>
<div>
    <label class="text-sm" for="{{ $p }}-email">E-mail</label>
    <input id="{{ $p }}-email" type="email" name="email" value="{{ $i?->email ?? old('email') }}" maxlength="255">
    @error('email')<p class="error">{{ $message }}</p>@enderror
</div>
<div>
    <label class="text-sm" for="{{ $p }}-phone">Téléphone</label>
    <input id="{{ $p }}-phone" type="text" name="phone" value="{{ $i?->phone ?? old('phone') }}" maxlength="40">
    @error('phone')<p class="error">{{ $message }}</p>@enderror
</div>
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-desc">Description</label>
    <textarea id="{{ $p }}-desc" name="description" rows="3" maxlength="2000">{{ $i?->description ?? old('description') }}</textarea>
    @error('description')<p class="error">{{ $message }}</p>@enderror
</div>
<div>
    <label class="text-sm" for="{{ $p }}-logo">{{ $i?->logo_path ? 'Nouveau logo (optionnel)' : 'Logo (jpg, png, webp — 3 Mo max)' }}</label>
    <input id="{{ $p }}-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
    @error('logo')<p class="error">{{ $message }}</p>@enderror
</div>
@if($i?->logo_path)
    <label style="display:flex;align-items:center;gap:8px;align-self:end">
        <input type="checkbox" name="remove_logo" value="1">
        <span class="text-sm">Retirer le logo</span>
    </label>
@endif
<label style="display:flex;align-items:center;gap:8px;align-self:end">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked($i ? $i->is_active : true)>
    <span class="text-sm">Actif (proposé dans les fiches de clubs et les profils)</span>
</label>
