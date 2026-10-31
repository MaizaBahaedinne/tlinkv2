@if($editable)
    <div class="form-grid">
        <x-admin.textarea name="summary" label="Bilan de l’activité" :value="$report->summary" :maxlength="5000" :rows="8" hint="Déroulement, objectifs atteints, difficultés rencontrées. Obligatoire pour soumettre." />
    </div>
@else
    <p class="text-sm" style="white-space:pre-line">{{ $report->summary ?: '—' }}</p>
@endif
