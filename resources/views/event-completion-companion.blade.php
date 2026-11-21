{{-- Ligne d'accompagnant de la fiche inscrit. Variables : $index, $values, $relationships, $input, $label. --}}
<div class="grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-3" data-companion-row>
    <div>
        <label class="{{ $label }}">Nom *</label>
        <input type="text" name="companions[{{ $index }}][last_name]" value="{{ $values['last_name'] ?? '' }}" maxlength="120" class="{{ $input }}">
    </div>
    <div>
        <label class="{{ $label }}">Prénom *</label>
        <input type="text" name="companions[{{ $index }}][first_name]" value="{{ $values['first_name'] ?? '' }}" maxlength="120" class="{{ $input }}">
    </div>
    <div>
        <label class="{{ $label }}">Lien familial *</label>
        <select name="companions[{{ $index }}][relationship]" class="{{ $input }}">
            <option value="">Choisir…</option>
            @foreach ($relationships as $code => $name)
                <option value="{{ $code }}" @selected(($values['relationship'] ?? '') === $code)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="text-right sm:col-span-3"><button type="button" class="text-xs font-semibold text-rose-600 underline" data-companion-remove>Retirer cet accompagnant</button></div>
</div>
