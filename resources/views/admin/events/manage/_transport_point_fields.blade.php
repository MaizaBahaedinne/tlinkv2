{{-- Champs d'un point de départ / collecte. Variable : $point (null pour un nouveau point). --}}
<div class="field">
    <label>Région <span class="req">*</span></label>
    <select name="region_id" required>
        @foreach($regions as $region)<option value="{{ $region->id }}" @selected((int) old('region_id', $point?->region_id) === $region->id)>{{ $region->name }}</option>@endforeach
    </select>
</div>
<div class="field"><label>Ville <span class="req">*</span></label><input name="city" required maxlength="120" value="{{ $point ? $point->city : old('city') }}"></div>
<div class="field"><label>Nom du point <span class="req">*</span></label><input name="name" required maxlength="160" value="{{ $point ? $point->name : old('name') }}" placeholder="Gare routière, Place centrale…"></div>
<div class="field"><label>Adresse</label><input name="address" maxlength="255" value="{{ $point ? $point->address : old('address') }}"></div>
<div class="field"><label>Latitude (GPS)</label><input name="latitude" inputmode="decimal" value="{{ $point ? $point->latitude : old('latitude') }}" placeholder="34.7400000"></div>
<div class="field"><label>Longitude (GPS)</label><input name="longitude" inputmode="decimal" value="{{ $point ? $point->longitude : old('longitude') }}" placeholder="10.7600000"></div>
