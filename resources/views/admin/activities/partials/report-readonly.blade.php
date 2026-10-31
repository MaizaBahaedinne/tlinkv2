<article class="card">
    <div class="card-head"><div><h2>Bilan</h2></div></div>
    <div class="card-body text-sm">
        <p style="white-space:pre-line">{{ $report->summary ?: '—' }}</p>
    </div>
</article>

<article class="card card-flush">
    <div class="card-head"><div><h2>Ressources humaines</h2><div class="sub">Implication des membres participants</div></div></div>
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
        <div class="card-body text-sm text-muted">Aucun membre noté.</div>
    @endif
    @if($report->hr_summary)<div class="card-body text-sm"><strong>Appréciation générale :</strong> {{ $report->hr_summary }}</div>@endif
</article>

<article class="card">
    <div class="card-head"><div><h2>Marketing</h2></div></div>
    <div class="card-body text-sm">
        @foreach($report->posters() as $poster)
            @if($poster->fileUrl())<p><strong>Affiche</strong></p><p><a href="{{ $poster->fileUrl() }}" target="_blank" rel="noopener"><img src="{{ $poster->fileUrl() }}" alt="Affiche de l’activité" style="max-height:160px;border-radius:8px"></a></p>@endif
        @endforeach
        <p style="margin-top:10px"><strong>Preuves de l’activité</strong></p>
        <div style="display:flex;flex-wrap:wrap;gap:12px">
            @forelse($report->proofs() as $proof)
                @if($proof->fileUrl())<a href="{{ $proof->fileUrl() }}" target="_blank" rel="noopener"><img src="{{ $proof->fileUrl() }}" alt="Preuve de l’activité" style="height:90px;border-radius:8px"></a>@endif
            @empty
                <span class="text-muted">Aucune.</span>
            @endforelse
        </div>
        <p style="margin-top:14px"><strong>Retombées médias</strong></p>
        @forelse($report->mediaLinks() as $item)
            <p>{{ \App\Models\ActivityReport::MEDIA_KINDS[$item->kind] ?? $item->kind }}@if($item->label) — {{ $item->label }}@endif @if($item->url)· <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer">Voir</a>@endif</p>
        @empty
            <p class="text-muted">Aucune retombée déclarée.</p>
        @endforelse
    </div>
</article>

<article class="card card-flush">
    <div class="card-head"><div><h2>Événements et sponsoring</h2><div class="sub">Partenaires de l’activité</div></div></div>
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
        <div class="card-body text-sm text-muted">Aucun partenaire déclaré.</div>
    @endif
</article>

<article class="card">
    <div class="card-head"><div><h2>Budget</h2></div></div>
    <div class="card-body text-sm">
        <p><strong>Prévisionnel :</strong> {{ $money($report->budget_planned) }}</p>
        <p><strong>Dépenses réelles :</strong> {{ $money($report->budget_spent) }}</p>
        <p><strong>Recettes (hors sponsoring) :</strong> {{ $money($report->budget_income) }}</p>
        <p><strong>Sponsoring déclaré :</strong> {{ $money($report->sponsoredAmount()) }}</p>
        @if($report->budget_notes)<p style="margin-top:8px">{{ $report->budget_notes }}</p>@endif
    </div>
</article>
