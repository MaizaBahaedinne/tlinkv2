{{-- Champs d'un point de l'ordre du jour. Variables : $item, $attendeeOptions (user_id => nom). --}}
<div class="field span-2"><label>Intitulé</label><input type="text" name="title" value="{{ $item->title }}" maxlength="190" required></div>
<div class="field"><label>Durée (min)</label><input type="number" name="duration_minutes" value="{{ $item->duration_minutes }}" min="1" max="600"></div>
<div class="field"><label>Présenté par</label>
    <select name="presenter_id">
        <option value="">—</option>
        @foreach($attendeeOptions as $id => $name)<option value="{{ $id }}" @selected($item->presenter_id === $id)>{{ $name }}</option>@endforeach
    </select>
</div>
<div class="field span-2"><label>Description</label><textarea name="description" rows="2" maxlength="3000">{{ $item->description }}</textarea></div>
<div class="field"><label>Issue</label>
    <select name="outcome">
        @foreach(\App\Models\MeetingAgendaItem::OUTCOMES as $key => $label)<option value="{{ $key }}" @selected($item->outcome === $key)>{{ $label }}</option>@endforeach
    </select>
</div>
<div class="field span-2"><label>Décision prise</label><textarea name="decision" rows="2" maxlength="5000" placeholder="Ce qui a été décidé, qui fait quoi, pour quand…">{{ $item->decision }}</textarea></div>
