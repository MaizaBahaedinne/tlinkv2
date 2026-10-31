{{-- Champs d'un mode de livraison (création et modification). --}}
<div class="field"><label>Nom</label><input type="text" name="name" value="{{ $method->name }}" maxlength="100" required></div>
<div class="field">
    <label>Type</label>
    <select name="kind">
        @foreach(\App\Models\StoreDeliveryMethod::KINDS as $value => $label)
            <option value="{{ $value }}" @selected($method->kind === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="field"><label>Frais de base (TND)</label><input type="number" name="fee" value="{{ $method->fee }}" step="0.01" min="0"><p class="hint">Ignoré pour un retrait sur place.</p></div>
<div class="field"><label>Gratuit dès (TND)</label><input type="number" name="free_above" value="{{ $method->free_above }}" step="0.01" min="0"><p class="hint">Livraison offerte à partir de ce sous-total (facultatif).</p></div>
<div class="field"><label>Délai estimé</label><input type="text" name="estimated_delay" value="{{ $method->estimated_delay }}" maxlength="100" placeholder="2 à 4 jours ouvrés"></div>
<div class="field"><label>Ordre</label><input type="number" name="sort_order" value="{{ $method->sort_order }}" min="0"></div>
<div class="field span-2"><label>Consignes affichées au membre (adresse de retrait, horaires…)</label><textarea name="instructions" rows="2" maxlength="3000">{{ $method->instructions }}</textarea></div>
<div class="field span-2">
    <label>Tarif par gouvernorat (TND) — laissez vide pour appliquer les frais de base</label>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:6px">
        @foreach($governorates as $governorate)
            <label style="font-weight:400;display:flex;gap:6px;align-items:center;justify-content:space-between">
                {{ $governorate->name }}
                <input type="number" name="governorate_fees[{{ $governorate->id }}]" value="{{ ($method->governorate_fees ?? [])[(string) $governorate->id] ?? '' }}" step="0.01" min="0" style="width:80px">
            </label>
        @endforeach
    </div>
</div>
<div class="field span-2"><label style="font-weight:400"><input type="checkbox" name="is_active" value="1" @checked($method->is_active)> Activé (proposé aux membres)</label></div>
