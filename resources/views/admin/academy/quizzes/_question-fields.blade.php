@php
    $p = $prefix;
    $mine = old('_form') === $p;
    $val = fn (string $field, $default = null) => $mine ? old($field, $default) : $default;
    $err = fn (string $field): ?string => $mine ? $errors->first($field) : null;
    $existing = $question ? $question->options->values() : collect();
    $slots = max(6, $existing->count() + 1);
@endphp
<input type="hidden" name="_form" value="{{ $p }}">
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-text">Question</label>
    <textarea id="{{ $p }}-text" name="text" rows="2" maxlength="500" required>{{ $val('text', $question?->text) }}</textarea>
    @if($err('text'))<p class="error">{{ $err('text') }}</p>@endif
</div>
<div>
    <label class="text-sm" for="{{ $p }}-type">Type de question</label>
    <select id="{{ $p }}-type" name="type" required>
        @foreach(\App\Models\AcademyQuizQuestion::TYPES as $value => $label)
            <option value="{{ $value }}" @selected($val('type', $question?->type ?? 'single') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @if($err('type'))<p class="error">{{ $err('type') }}</p>@endif
</div>
<div style="grid-column:1/-1">
    <p class="text-sm" style="margin:0 0 6px">Réponses proposées — cochez la ou les bonnes réponses. Les lignes vides sont ignorées, l’ordre est mélangé pour chaque tentative.</p>
    @for($i = 0; $i < $slots; $i++)
        @php
            $row = $existing[$i] ?? null;
            $rowText = $mine ? old('options.'.$i.'.text') : $row?->text;
            $rowCorrect = $mine ? (bool) old('options.'.$i.'.correct') : (bool) $row?->is_correct;
        @endphp
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
            <input type="hidden" name="options[{{ $i }}][correct]" value="0">
            <input type="checkbox" name="options[{{ $i }}][correct]" value="1" @checked($rowCorrect) aria-label="Bonne réponse {{ $i + 1 }}" style="width:auto;flex:none">
            <input type="text" name="options[{{ $i }}][text]" value="{{ $rowText }}" maxlength="255" placeholder="Réponse {{ $i + 1 }}" aria-label="Texte de la réponse {{ $i + 1 }}">
        </div>
    @endfor
    @if($err('options'))<p class="error">{{ $err('options') }}</p>@endif
</div>
<div style="grid-column:1/-1">
    <label class="text-sm" for="{{ $p }}-explanation">Explication affichée à la correction (facultatif)</label>
    <textarea id="{{ $p }}-explanation" name="explanation" rows="2" maxlength="1000">{{ $val('explanation', $question?->explanation) }}</textarea>
    @if($err('explanation'))<p class="error">{{ $err('explanation') }}</p>@endif
</div>
