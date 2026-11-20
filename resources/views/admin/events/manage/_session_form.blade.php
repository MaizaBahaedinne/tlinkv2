@php
    $dt = fn ($value) => $value?->format('Y-m-d\TH:i');
    $isNew = ! $session->exists;
    $selected = $isNew ? [] : $session->speakers->pluck('id')->all();
@endphp
<form method="post" action="{{ $action }}" class="form-grid">
    @csrf
    @if(! $isNew) @method('PUT') @endif
    <div class="field">
        <label>Type d’étape <span class="req">*</span></label>
        <select name="type" required>
            @foreach(\App\Models\EventSession::TYPES as $code => $label)
                <option value="{{ $code }}" @selected($session->type === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>Titre <span class="req">*</span></label><input name="title" value="{{ $session->title }}" required maxlength="255"></div>
    <div class="field"><label>Début <span class="req">*</span></label><input type="datetime-local" name="starts_at" value="{{ $dt($session->starts_at) }}" required></div>
    <div class="field"><label>Fin</label><input type="datetime-local" name="ends_at" value="{{ $dt($session->ends_at) }}"></div>
    <div class="field">
        <label>Lieu <span class="req">*</span></label>
        <select name="venue_mode" required>
            @foreach(\App\Models\EventSession::VENUE_MODES as $code => $label)
                <option value="{{ $code }}" @selected(($session->venue_mode ?? 'event') === $code)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="hint">« {{ $event->location ?: 'lieu de l’événement non renseigné' }} » si la salle est dans le lieu de l’événement.</p>
    </div>
    <div class="field"><label>Salle / espace (lieu de l’événement)</label><input name="room" value="{{ $session->room }}" maxlength="120" placeholder="Amphi A, Hall…"></div>
    <div class="field"><label>Nom du lieu externe</label><input name="venue_name" value="{{ $session->venue_name }}" maxlength="255" placeholder="Hôtel, restaurant…"></div>
    <div class="field"><label>Adresse du lieu externe</label><input name="venue_address" value="{{ $session->venue_address }}" maxlength="255"></div>
    <div class="field">
        <label>Inscription</label>
        <input type="hidden" name="requires_registration" value="0">
        <label class="check" style="margin-top:10px"><input type="checkbox" name="requires_registration" value="1" @checked($session->requires_registration)> Inscription requise à cette étape</label>
    </div>
    <div class="field"><label>Places (si inscription)</label><input type="number" name="capacity" min="1" value="{{ $session->capacity }}" placeholder="Illimitées"></div>
    <div class="field span-2"><label>Description</label><textarea name="description" rows="2" maxlength="3000">{{ $session->description }}</textarea></div>
    @if($speakers->count())
        <div class="field span-2">
            <label>Intervenants</label>
            <div style="display:flex;flex-wrap:wrap;gap:6px 16px">
                @foreach($speakers as $speaker)
                    <label class="check"><input type="checkbox" name="speaker_ids[]" value="{{ $speaker->id }}" @checked(in_array($speaker->id, $selected, true))> {{ $speaker->name }} <span class="text-sm text-muted">({{ \App\Models\EventSpeaker::KINDS[$speaker->kind] ?? $speaker->kind }})</span></label>
                @endforeach
            </div>
        </div>
    @endif
    <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> {{ $isNew ? 'Ajouter au programme' : 'Enregistrer' }}</button></div>
</form>
