{{-- Champs d'un mode de livraison (création et modification). Attend $method et $governorates. --}}
@php
    $uid = 'dm-'.($method->id ?? 'new');
@endphp
<div class="field">
    <label for="{{ $uid }}-name">Nom <span class="req">*</span></label>
    <input id="{{ $uid }}-name" type="text" name="name" value="{{ $method->name }}" maxlength="100" required placeholder="Livraison à domicile">
</div>
<div class="field">
    <label for="{{ $uid }}-kind">Type</label>
    <select id="{{ $uid }}-kind" name="kind">
        @foreach(\App\Models\StoreDeliveryMethod::KINDS as $value => $label)
            <option value="{{ $value }}" @selected($method->kind === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="field">
    <label for="{{ $uid }}-fee">Frais de base (TND)</label>
    <input id="{{ $uid }}-fee" type="number" name="fee" value="{{ $method->fee }}" step="0.01" min="0">
    <p class="hint">Sans effet pour un retrait sur place (toujours gratuit).</p>
</div>
<div class="field">
    <label for="{{ $uid }}-free">Livraison offerte dès (TND)</label>
    <input id="{{ $uid }}-free" type="number" name="free_above" value="{{ $method->free_above }}" step="0.01" min="0">
    <p class="hint">Facultatif : sous-total à partir duquel la livraison est gratuite.</p>
</div>
<div class="field">
    <label for="{{ $uid }}-delay">Délai estimé</label>
    <input id="{{ $uid }}-delay" type="text" name="estimated_delay" value="{{ $method->estimated_delay }}" maxlength="100" placeholder="2 à 4 jours ouvrés">
</div>
<div class="field">
    <label for="{{ $uid }}-sort">Ordre d’affichage</label>
    <input id="{{ $uid }}-sort" type="number" name="sort_order" value="{{ $method->sort_order ?? 0 }}" min="0">
</div>
<div class="field span-2">
    <label for="{{ $uid }}-instructions">Consignes affichées au membre</label>
    <textarea id="{{ $uid }}-instructions" name="instructions" rows="2" maxlength="3000" placeholder="Adresse de retrait, horaires, précautions…">{{ $method->instructions }}</textarea>
</div>
<div class="field span-2">
    <label>Tarif par gouvernorat (TND)</label>
    <div class="fee-grid">
        @foreach($governorates as $governorate)
            <label>
                {{ $governorate->name }}
                <input type="number" name="governorate_fees[{{ $governorate->id }}]" value="{{ ($method->governorate_fees ?? [])[(string) $governorate->id] ?? '' }}" step="0.01" min="0" placeholder="{{ number_format((float) $method->fee, 2, ',', '') }}">
            </label>
        @endforeach
    </div>
    <p class="hint">Laissez vide pour appliquer les frais de base.</p>
</div>
<div class="field span-2">
    <label class="check check-card">
        <input type="checkbox" name="is_active" value="1" @checked($method->is_active)>
        <span>Activé<small>Proposé aux membres au moment de la commande.</small></span>
    </label>
</div>
