@php
    $money = fn ($value) => $value === null ? '—' : number_format((float) $value, 3, ',', ' ').' DT';
    $pts = fn ($value) => $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',');
    $self = $report->selfEvaluation();
    $feedback = $report->participantFeedback();
    $avg = fn ($value) => $value === null ? '—' : number_format($value, 1, ',', '').'/5';
@endphp
<details class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
    <summary class="cursor-pointer text-sm font-semibold text-tlink-blue">Compte rendu du club (validé le {{ $report->validated_at?->format('d/m/Y') }})</summary>

    <div class="mt-4 space-y-5 text-sm text-slate-700">
        <div>
            <h3 class="font-semibold text-slate-900">Bilan</h3>
            <p class="whitespace-pre-line">{{ $report->summary ?: '—' }}</p>
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">Auto-évaluation du club (indicative, hors note)</h3>
            @if($self['rows']->isEmpty())
                <p class="text-slate-500">Non renseignée.</p>
            @else
                <ul class="mt-1 space-y-1">
                    @foreach($self['rows'] as $row)
                        <li><strong>{{ $row['criterion']->label }}</strong> : {{ $pts($row['points']) }}/{{ $row['criterion']->max_points }}@if($row['comment']) — {{ $row['comment'] }}@endif</li>
                    @endforeach
                </ul>
                <p class="mt-1"><strong>Total :</strong> {{ $pts($self['total']) }}/{{ $self['max'] }}</p>
            @endif
            @if($report->self_strengths)<p class="mt-1"><strong>Points forts :</strong> {{ $report->self_strengths }}</p>@endif
            @if($report->self_improvements)<p class="mt-1"><strong>Axes d’amélioration :</strong> {{ $report->self_improvements }}</p>@endif
        </div>

        <div>
            <h3 class="font-semibold text-slate-900">Avis des participants</h3>
            @if($feedback['count'] === 0)
                <p class="text-slate-500">Aucun avis recueilli.</p>
            @else
                <p>{{ $feedback['count'] }} avis pour {{ $feedback['present'] }} participants présents ({{ $feedback['registered'] }} inscrits) : satisfaction globale {{ $avg($feedback['overall']) }}, organisation {{ $avg($feedback['organization']) }}, contenu {{ $avg($feedback['content']) }}.</p>
                @foreach($feedback['comments'] as $comment)
                    <p class="mt-1 border-l-2 border-slate-300 pl-3 italic">{{ $comment }}</p>
                @endforeach
            @endif
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
            @foreach($report->proofs() as $proof)
                @if($proof->href())<p><a href="{{ $proof->href() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-tlink-blue hover:underline">Preuve de l’activité (publication Facebook)</a></p>@endif
            @endforeach
            @foreach($report->posters() as $poster)
                @if($poster->href())<p><a href="{{ $poster->href() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-tlink-blue hover:underline">Affiche de l’activité</a></p>@endif
            @endforeach
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
