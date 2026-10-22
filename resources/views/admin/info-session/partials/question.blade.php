@php
    $options = $q['options'] ?? [];
    $rows = max($slots, count($options));
@endphp
<article class="card card-flush" data-question>
    <div class="card-head">
        <div><h2>Question <span data-num></span></h2></div>
        @if($canEdit)
            <button type="button" class="btn btn-ghost btn-sm" data-remove-question>Supprimer</button>
        @endif
    </div>
    <fieldset @disabled(! $canEdit) style="border:0;margin:0;padding:0;min-width:0">
        <div class="card-body">
            <input type="hidden" name="questions[{{ $i }}][id]" value="{{ $q['id'] ?? '' }}">
            <div class="form-grid">
                <div class="field span-2">
                    <label>Intitulé</label>
                    <input type="text" name="questions[{{ $i }}][text]" value="{{ $q['text'] ?? '' }}" maxlength="500">
                </div>
                <div class="field span-2">
                    <label>Type de réponse</label>
                    <select name="questions[{{ $i }}][type]">
                        <option value="single" @selected(($q['type'] ?? 'single') === 'single')>Une seule bonne réponse</option>
                        <option value="multiple" @selected(($q['type'] ?? 'single') === 'multiple')>Plusieurs bonnes réponses (cases à cocher)</option>
                    </select>
                </div>
            </div>

            <div class="table-wrap" style="margin-top:14px">
                <table class="tbl">
                    <thead><tr><th style="width:90px">Bonne réponse</th><th>Réponse proposée</th></tr></thead>
                    <tbody>
                        @for($j = 0; $j < $rows; $j++)
                            <tr>
                                <td style="text-align:center"><input type="checkbox" name="questions[{{ $i }}][options][{{ $j }}][correct]" value="1" @checked(! empty($options[$j]['correct'])) aria-label="Bonne réponse"></td>
                                <td><input type="text" name="questions[{{ $i }}][options][{{ $j }}][text]" value="{{ $options[$j]['text'] ?? '' }}" maxlength="300" style="width:100%" placeholder="{{ $j < 2 ? 'Réponse (obligatoire)' : 'Réponse (facultative)' }}"></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    </fieldset>
</article>
