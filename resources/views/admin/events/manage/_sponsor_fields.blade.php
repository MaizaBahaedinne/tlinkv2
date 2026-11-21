{{-- Champs d'un sponsor / partenaire. Variables : $formId (id de la modale), $sponsor (null si nouveau), $levels, $directorySponsors, $directoryPartners. --}}
@php
    $isNew = $sponsor === null;
    $o = fn (string $key, $default = null) => old('_form') === $formId ? old($key, $default) : $default;
    $hasDirectory = $isNew && (($directorySponsors ?? collect())->isNotEmpty() || ($directoryPartners ?? collect())->isNotEmpty());
    $relationLabels = \App\Models\Sponsor::RELATIONS;
@endphp
@if($hasDirectory)
    <div class="field" style="grid-column:1/-1">
        <label for="{{ $formId }}-directory">Choisir dans ma base de partenaires</label>
        <select id="{{ $formId }}-directory" name="directory">
            <option value="">— Saisir un nouveau sponsor / partenaire —</option>
            @if($directorySponsors->isNotEmpty())
                <optgroup label="Carnet de partenaires">
                    @foreach($directorySponsors as $entry)
                        <option value="s:{{ $entry->id }}" @selected($o('directory') === 's:'.$entry->id)>{{ $entry->name }} — {{ $relationLabels[$entry->relation] ?? $entry->relation }}{{ $entry->city ? ' ('.$entry->city.')' : '' }}</option>
                    @endforeach
                </optgroup>
            @endif
            @if($directoryPartners->isNotEmpty())
                <optgroup label="Partenaires du site (avec logo)">
                    @foreach($directoryPartners as $entry)
                        <option value="p:{{ $entry->id }}" @selected($o('directory') === 'p:'.$entry->id)>{{ $entry->name }}</option>
                    @endforeach
                </optgroup>
            @endif
        </select>
        <p class="hint">Le nom, le site web et le logo (partenaires du site) sont repris automatiquement ; ce que vous saisissez ci-dessous reste prioritaire.</p>
    </div>
@endif
<div class="field" style="grid-column:1/-1"><label>Nom @if(! $hasDirectory)<span class="req">*</span>@endif</label><input name="name" value="{{ $o('name', $sponsor?->name) }}" @if(! $hasDirectory) required @endif maxlength="160" @if($hasDirectory) placeholder="Repris du choix ci-dessus" @endif></div>
<div class="field">
    <label>Niveau <span class="req">*</span></label>
    <select name="level_id" required data-sponsor-level-select>
        @foreach($levels as $option)
            <option value="{{ $option->id }}" @selected((int) $o('level_id', $sponsor?->level_id) === $option->id)>{{ $option->name }} ({{ $option->kindLabel() }})</option>
        @endforeach
    </select>
</div>
<div class="field"><label>Site web</label><input type="url" name="website_url" value="{{ $o('website_url', $sponsor?->website_url) }}" maxlength="500" placeholder="https://"></div>
<div class="field" style="grid-column:1/-1">
    <label>Logo</label>
    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
    @if(! $isNew && $sponsor->logoUrl())
        <label class="check" style="margin-top:6px"><input type="checkbox" name="logo_remove" value="1"> Supprimer le logo actuel</label>
    @endif
</div>
<div class="field" style="grid-column:1/-1"><label>Description</label><textarea name="description" rows="2" maxlength="1000">{{ $o('description', $sponsor?->description) }}</textarea></div>
