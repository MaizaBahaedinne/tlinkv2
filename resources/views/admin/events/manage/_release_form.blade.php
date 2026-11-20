@php
    $dt = fn ($value) => $value?->format('Y-m-d\TH:i');
    $isNew = ! $release->exists;
@endphp
<form method="post" action="{{ $action }}" class="form-grid">
    @csrf
    @if(! $isNew) @method('PUT') @endif
    <div class="field"><label>Nom de la release <span class="req">*</span></label><input name="name" value="{{ $release->name }}" required maxlength="120" placeholder="Early bird, Standard, Late…"></div>
    <div class="field"><label>Prix (TND) <span class="req">*</span></label><input type="number" name="price" min="0" step="0.01" value="{{ $release->price !== null ? (float) $release->price : '0' }}" required></div>
    <div class="field"><label>Quantité (places)</label><input type="number" name="quantity" min="1" value="{{ $release->quantity }}" placeholder="Illimitée"></div>
    <div class="field">
        <label>Active</label>
        <input type="hidden" name="is_active" value="0">
        <label class="check" style="margin-top:10px"><input type="checkbox" name="is_active" value="1" @checked($isNew || $release->is_active)> Ouverte à l’inscription</label>
    </div>
    <div class="field"><label>Ouverture</label><input type="datetime-local" name="opens_at" value="{{ $dt($release->opens_at) }}"></div>
    <div class="field"><label>Clôture</label><input type="datetime-local" name="closes_at" value="{{ $dt($release->closes_at) }}"></div>
    <div class="field span-2"><label>Description</label><textarea name="description" rows="2" maxlength="1000">{{ $release->description }}</textarea></div>
    <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $isNew ? 'Ajouter la release' : 'Enregistrer' }}</button></div>
</form>
