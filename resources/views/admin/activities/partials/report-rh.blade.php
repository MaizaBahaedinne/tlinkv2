@if($editable)
    <p class="text-sm text-muted">Notez l’implication de chaque membre des clubs organisateurs qui a participé à l’activité (1 = faible, 5 = exceptionnelle).</p>
    @php
        $members = array_pad($memberRows, max(3, count($memberRows) + 1), []);
    @endphp
    <div class="field {{ $errors->has('members') ? 'has-error' : '' }}">
        <div data-rows="members">
            @foreach($members as $i => $row)
                <div class="form-grid" style="margin-top:8px" data-row>
                    <div class="field {{ $errors->has('members.'.$i.'.user_id') ? 'has-error' : '' }}">
                        <select name="members[{{ $i }}][user_id]" aria-label="Membre">
                            <option value="">Membre…</option>
                            @foreach($candidates as $candidate)<option value="{{ $candidate->id }}" @selected((string) ($row['user_id'] ?? '') === (string) $candidate->id)>{{ $candidate->name }}</option>@endforeach
                        </select>
                        @error('members.'.$i.'.user_id')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field {{ $errors->has('members.'.$i.'.implication') ? 'has-error' : '' }}">
                        <select name="members[{{ $i }}][implication]" aria-label="Implication">
                            <option value="">Implication…</option>
                            @foreach(\App\Models\ActivityReport::IMPLICATION as $score => $label)<option value="{{ $score }}" @selected((string) ($row['implication'] ?? '') === (string) $score)>{{ $score }} — {{ $label }}</option>@endforeach
                        </select>
                        @error('members.'.$i.'.implication')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field"><input type="text" name="members[{{ $i }}][role]" maxlength="120" placeholder="Rôle (ex. logistique, accueil)" aria-label="Rôle" value="{{ $row['role'] ?? '' }}"></div>
                    <div class="field"><input type="text" name="members[{{ $i }}][comment]" maxlength="400" placeholder="Commentaire (facultatif)" aria-label="Commentaire" value="{{ $row['comment'] ?? '' }}"></div>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-add-row="members" style="margin-top:8px">+ Ajouter un membre</button>
        @if($candidates->isEmpty())<p class="hint">Aucun membre validé n’est rattaché aux clubs organisateurs.</p>@endif
        @error('members')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div class="form-grid" style="margin-top:14px">
        <x-admin.textarea name="hr_summary" label="Appréciation générale de l’équipe" :value="$report->hr_summary" :maxlength="3000" :rows="3" />
    </div>
@else
    @if($report->members->isNotEmpty())
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Membre</th><th>Rôle</th><th>Implication</th><th>Commentaire</th></tr></thead>
                <tbody>
                    @foreach($report->members as $member)
                        <tr>
                            <td class="cell-main">{{ $member->user?->name ?? 'Utilisateur supprimé' }}</td>
                            <td>{{ $member->role ?: '—' }}</td>
                            <td>{{ $member->implication }} / 5 — {{ \App\Models\ActivityReport::IMPLICATION[$member->implication] ?? '' }}</td>
                            <td class="text-sm">{{ $member->comment ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-muted">Aucun membre noté.</p>
    @endif
    @if($report->hr_summary)<p class="text-sm" style="margin-top:12px"><strong>Appréciation générale :</strong> {{ $report->hr_summary }}</p>@endif
@endif
