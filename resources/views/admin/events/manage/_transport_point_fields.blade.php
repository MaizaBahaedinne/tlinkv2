{{-- Champs d'un point de départ / collecte. Variables : $formId (id de la modale), $point (null si nouveau), $regions, $region (région imposée, optionnel). --}}
@php
    $o = fn (string $key, $default = null) => old('_form') === $formId ? old($key, $default) : $default;
@endphp
@if(isset($region))
    <input type="hidden" name="region_id" value="{{ $region->id }}">
@else
    <div class="field">
        <label>Région <span class="req">*</span></label>
        <select name="region_id" required>
            @foreach($regions as $option)<option value="{{ $option->id }}" @selected((int) $o('region_id', $point?->region_id) === $option->id)>{{ $option->name }}</option>@endforeach
        </select>
    </div>
@endif
<div class="field"><label>Ville <span class="req">*</span></label><input name="city" required maxlength="120" value="{{ $o('city', $point?->city) }}"></div>
<div class="field" style="grid-column:1/-1"><label>Nom du point <span class="req">*</span></label><input name="name" required maxlength="160" value="{{ $o('name', $point?->name) }}" placeholder="Gare routière, Place centrale…"></div>
<div class="field" style="grid-column:1/-1"><label>Adresse</label><input name="address" maxlength="255" value="{{ $o('address', $point?->address) }}"></div>

<div class="field" style="grid-column:1/-1" data-geo-picker>
    <label>Position sur la carte</label>
    <div class="geo-search">
        <input type="search" data-geo-query maxlength="120" placeholder="Rechercher un lieu (gare, ville, adresse…)" aria-label="Rechercher un lieu">
        <button type="button" class="btn btn-secondary btn-sm" data-geo-search>Rechercher</button>
        <button type="button" class="btn btn-ghost btn-sm" data-geo-locate>Ma position</button>
    </div>
    <ul class="geo-results" data-geo-results hidden></ul>
    <div class="geo-map" data-geo-map></div>
    <p class="hint">Cliquez sur la carte ou déplacez le repère pour fixer le point exact. <button type="button" class="geo-clear" data-geo-clear>Effacer la position</button></p>
</div>
<div class="field"><label>Latitude (GPS)</label><input name="latitude" inputmode="decimal" value="{{ $o('latitude', $point?->latitude) }}" placeholder="34.7400000"></div>
<div class="field"><label>Longitude (GPS)</label><input name="longitude" inputmode="decimal" value="{{ $o('longitude', $point?->longitude) }}" placeholder="10.7600000"></div>
