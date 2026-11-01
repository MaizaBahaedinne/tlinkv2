@if($editable)
    <p class="text-sm text-muted">Les membres des clubs organisateurs sont listés automatiquement. Notez uniquement ceux qui ont réellement participé (1 = faible, 5 = exceptionnelle).</p>
    @php($memberMap = collect($memberRows)->keyBy('user_id'))
    @if($candidates->isEmpty())
        <p class="hint">Aucun membre validé n’est rattaché aux clubs organisateurs.</p>
    @else
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Membre</th><th>Rôle</th><th>Implication</th><th>Commentaire</th></tr></thead>
                <tbody>
                    @foreach($candidates as $candidate)
                        @php($row = (array) ($memberMap->get($candidate->id) ?? []))
                        <tr>
                            <td class="cell-main">
                                <input type="hidden" name="members[{{ $candidate->id }}][user_id]" value="{{ $candidate->id }}">
                                {{ $candidate->name }}
                                @error('members.'.$candidate->id.'.user_id')<p class="error">{{ $message }}</p>@enderror
                            </td>
                            <td><input type="text" name="members[{{ $candidate->id }}][role]" maxlength="120" placeholder="Rôle (facultatif)" aria-label="Rôle {{ $candidate->name }}" value="{{ old('members.'.$candidate->id.'.role', $row['role'] ?? '') }}"></td>
                            <td class="{{ $errors->has('members.'.$candidate->id.'.implication') ? 'has-error' : '' }}">
                                <select name="members[{{ $candidate->id }}][implication]" aria-label="Implication {{ $candidate->name }}">
                                    <option value="">—</option>
                                    @foreach(\App\Models\ActivityReport::IMPLICATION as $score => $label)
                                        <option value="{{ $score }}" @selected((string) old('members.'.$candidate->id.'.implication', $row['implication'] ?? '') === (string) $score)>{{ $score }} — {{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('members.'.$candidate->id.'.implication')<p class="error">{{ $message }}</p>@enderror
                            </td>
                            <td><input type="text" name="members[{{ $candidate->id }}][comment]" maxlength="400" placeholder="Commentaire (facultatif)" aria-label="Commentaire {{ $candidate->name }}" value="{{ old('members.'.$candidate->id.'.comment', $row['comment'] ?? '') }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @error('members')<p class="error">{{ $message }}</p>@enderror
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
