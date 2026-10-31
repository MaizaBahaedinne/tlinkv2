@php
    $pts = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',');
@endphp

<p class="text-sm text-muted">
    Le club s’évalue lui-même avec le barème du défi ({{ $activity->in_theme ? 'activité dans la thématique' : 'activité hors thématique' }}). Cette auto-évaluation éclaire le comité mais ne compte pas dans la note de l’activité.
</p>

@if($self['rows']->isEmpty())
    <p class="text-sm text-muted">Aucun barème défini pour ce type d’activité.</p>
@elseif($editable)
    <div class="field {{ $errors->has('self') ? 'has-error' : '' }}">
        @foreach($self['rows'] as $row)
            @php($criterion = $row['criterion'])
            <div class="form-grid" style="margin-top:10px">
                <div class="field {{ $errors->has('self.'.$criterion->id.'.points') ? 'has-error' : '' }}">
                    <label for="f-self-{{ $criterion->id }}">{{ $criterion->label }} <span class="req">*</span> <span class="text-muted">(sur {{ $criterion->max_points }})</span></label>
                    @if($criterion->description)<p class="hint" style="margin:0 0 4px">{{ $criterion->description }}</p>@endif
                    <input id="f-self-{{ $criterion->id }}" type="number" name="self[{{ $criterion->id }}][points]" min="0" max="{{ $criterion->max_points }}" step="0.25" value="{{ old('self.'.$criterion->id.'.points', $row['points']) }}">
                    @error('self.'.$criterion->id.'.points')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label for="f-self-c-{{ $criterion->id }}">Justification</label>
                    <input id="f-self-c-{{ $criterion->id }}" type="text" name="self[{{ $criterion->id }}][comment]" maxlength="500" placeholder="Pourquoi cette note ? (facultatif)" value="{{ old('self.'.$criterion->id.'.comment', $row['comment']) }}">
                </div>
            </div>
        @endforeach
        @error('self')<p class="error">{{ $message }}</p>@enderror
    </div>

    <div class="form-grid" style="margin-top:16px">
        <x-admin.textarea name="self_strengths" label="Points forts de l’activité" :value="$report->self_strengths" :maxlength="3000" :rows="4" />
        <x-admin.textarea name="self_improvements" label="Axes d’amélioration" :value="$report->self_improvements" :maxlength="3000" :rows="4" />
    </div>
@else
    <div class="table-wrap">
        <table class="tbl">
            <thead><tr><th>Critère</th><th>Auto-évaluation</th><th>Justification</th></tr></thead>
            <tbody>
                @foreach($self['rows'] as $row)
                    <tr>
                        <td class="cell-main">{{ $row['criterion']->label }}</td>
                        <td>{{ $pts($row['points']) }} / {{ $row['criterion']->max_points }}</td>
                        <td class="text-sm">{{ $row['comment'] ?: '—' }}</td>
                    </tr>
                @endforeach
                <tr><td><strong>Total</strong></td><td><strong>{{ $pts($self['total']) }} / {{ $self['max'] }}</strong></td><td></td></tr>
            </tbody>
        </table>
    </div>
    <div class="text-sm" style="margin-top:14px">
        <p><strong>Points forts :</strong> {{ $report->self_strengths ?: '—' }}</p>
        <p><strong>Axes d’amélioration :</strong> {{ $report->self_improvements ?: '—' }}</p>
    </div>
@endif
