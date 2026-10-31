@if($editable)
    <h3 style="margin-top:0">Partenaires de l’activité</h3>
    <p class="text-sm text-muted">Partenaires et nature de leur soutien.</p>
    @php
        $partners = array_pad($partnerRows, max(2, count($partnerRows) + 1), []);
    @endphp
    <div class="field">
        <div data-rows="partners">
            @foreach($partners as $i => $row)
                <div class="form-grid" style="margin-top:8px" data-row>
                    <div class="field"><input type="text" name="partners[{{ $i }}][name]" maxlength="160" placeholder="Partenaire" aria-label="Partenaire" value="{{ $row['name'] ?? '' }}"></div>
                    <div class="field">
                        <select name="partners[{{ $i }}][nature]" aria-label="Nature du soutien">
                            @foreach(\App\Models\ActivityReport::NATURES as $nature => $label)<option value="{{ $nature }}" @selected(($row['nature'] ?? 'sponsor') === $nature)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field"><input type="number" name="partners[{{ $i }}][amount]" min="0" step="0.001" placeholder="Montant (DT, facultatif)" aria-label="Montant" value="{{ $row['amount'] ?? '' }}"></div>
                    <div class="field"><input type="text" name="partners[{{ $i }}][description]" maxlength="400" placeholder="Détail du soutien (facultatif)" aria-label="Détail" value="{{ $row['description'] ?? '' }}"></div>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-add-row="partners" style="margin-top:8px">+ Ajouter un partenaire</button>
    </div>

    <h3>Budget de l’activité</h3>
    <div class="form-grid">
        <x-admin.input name="budget_planned" type="number" label="Budget prévisionnel (DT)" :value="$report->budget_planned" min="0" step="0.001" />
        <x-admin.input name="budget_spent" type="number" label="Dépenses réelles (DT)" :value="$report->budget_spent" min="0" step="0.001" hint="Obligatoire pour soumettre (0 si aucune dépense)." />
        <x-admin.input name="budget_income" type="number" label="Recettes, hors sponsoring (DT)" :value="$report->budget_income" min="0" step="0.001" />
        <x-admin.textarea name="budget_notes" label="Commentaire sur le budget" :value="$report->budget_notes" :maxlength="3000" :rows="3" />
    </div>
@else
    <h3 style="margin-top:0">Partenaires de l’activité</h3>
    @if($report->partners->isNotEmpty())
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Partenaire</th><th>Nature</th><th>Montant</th><th>Détail</th></tr></thead>
                <tbody>
                    @foreach($report->partners as $partner)
                        <tr>
                            <td class="cell-main">{{ $partner->name }}</td>
                            <td>{{ $partner->natureLabel() }}</td>
                            <td>{{ $money($partner->amount) }}</td>
                            <td class="text-sm">{{ $partner->description ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-muted">Aucun partenaire déclaré.</p>
    @endif

    <h3>Budget</h3>
    <div class="text-sm">
        <p><strong>Prévisionnel :</strong> {{ $money($report->budget_planned) }}</p>
        <p><strong>Dépenses réelles :</strong> {{ $money($report->budget_spent) }}</p>
        <p><strong>Recettes (hors sponsoring) :</strong> {{ $money($report->budget_income) }}</p>
        <p><strong>Sponsoring déclaré :</strong> {{ $money($report->sponsoredAmount()) }}</p>
        @if($report->budget_notes)<p style="margin-top:8px">{{ $report->budget_notes }}</p>@endif
    </div>
@endif
