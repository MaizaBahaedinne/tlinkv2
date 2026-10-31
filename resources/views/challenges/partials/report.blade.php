@php
    $money = fn ($value) => $value === null ? '—' : number_format((float) $value, 3, ',', ' ').' DT';
@endphp
<details class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
    <summary class="cursor-pointer text-sm font-semibold text-tlink-blue">Compte rendu du club (validé le {{ $report->validated_at?->format('d/m/Y') }})</summary>

    <div class="mt-4 space-y-5 text-sm text-slate-700">
        <div>
            <h3 class="font-semibold text-slate-900">Bilan</h3>
            <p class="whitespace-pre-line">{{ $report->summary ?: '—' }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">Ressources humaines</h3>
            @if($report->members->isEmpty())
                <p class="text-slate-500">Aucun membre noté.</p>
            @else
                <ul class="mt-1 space-y-1">
                    @foreach($report->members as $member)
                        <li><strong>{{ $member->user?->name ?? 'Membre supprimé' }}</strong>@if($member->role) ({{ $member->role }})@endif : {{ $member->implication }}/5 — {{ \App\Models\ActivityReport::IMPLICATION[$member->implication] ?? '' }}@if($member->comment), {{ $member->comment }}@endif</li>
                    @endforeach
                </ul>
            @endif
            @if($report->hr_summary)<p class="mt-1">{{ $report->hr_summary }}</p>@endif
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">Marketing</h3>
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach($report->posters() as $poster)
                    @if($poster->fileUrl())<a href="{{ $poster->fileUrl() }}" target="_blank" rel="noopener"><img src="{{ $poster->fileUrl() }}" alt="Affiche de l’activité" class="h-28 rounded-lg border border-slate-200"></a>@endif
                @endforeach
                @foreach($report->proofs() as $proof)
                    @if($proof->fileUrl())<a href="{{ $proof->fileUrl() }}" target="_blank" rel="noopener"><img src="{{ $proof->fileUrl() }}" alt="Preuve de l’activité" class="h-28 rounded-lg border border-slate-200"></a>@endif
                @endforeach
            </div>
            @forelse($report->mediaLinks() as $item)
                <p class="mt-1">{{ \App\Models\ActivityReport::MEDIA_KINDS[$item->kind] ?? $item->kind }}@if($item->label) — {{ $item->label }}@endif @if($item->url)· <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-tlink-blue hover:underline">Voir</a>@endif</p>
            @empty
                <p class="mt-1 text-slate-500">Aucune retombée média déclarée.</p>
            @endforelse
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">Événements et sponsoring</h3>
            @forelse($report->partners as $partner)
                <p>{{ $partner->name }} — {{ $partner->natureLabel() }}@if($partner->amount !== null) · {{ $money($partner->amount) }}@endif @if($partner->description)· {{ $partner->description }}@endif</p>
            @empty
                <p class="text-slate-500">Aucun partenaire déclaré.</p>
            @endforelse
            <p class="mt-2">
                <strong>Budget :</strong> prévisionnel {{ $money($report->budget_planned) }}, dépenses {{ $money($report->budget_spent) }}, recettes {{ $money($report->budget_income) }}, sponsoring {{ $money($report->sponsoredAmount()) }}.
            </p>
            @if($report->budget_notes)<p>{{ $report->budget_notes }}</p>@endif
        </div>
    </div>
</details>
