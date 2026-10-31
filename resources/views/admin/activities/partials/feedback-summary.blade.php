@php
    $avg = fn ($value) => $value === null ? '—' : number_format($value, 1, ',', '').' / 5';
@endphp

<p class="text-sm text-muted">
    Avis recueillis automatiquement : chaque participant scanne le QR code de l’activité avec l’application T.Link et remplit le formulaire d’avis, ce qui valide sa participation. Les commentaires sont anonymes.
</p>

<div class="stats" style="margin:14px 0">
    <x-admin.stat label="Inscrits" :value="$feedback['registered']" tone="blue" />
    <x-admin.stat label="Participations validées" :value="$feedback['present']" tone="green" />
    <x-admin.stat label="Avis reçus" :value="$feedback['count']" tone="cyan" />
    <x-admin.stat label="Satisfaction globale" :value="$avg($feedback['overall'])" tone="amber" />
</div>

@if($feedback['count'] > 0)
    <div class="text-sm">
        <p><strong>Organisation :</strong> {{ $avg($feedback['organization']) }}</p>
        <p><strong>Contenu de l’activité :</strong> {{ $avg($feedback['content']) }}</p>
        <p style="margin-top:12px"><strong>Commentaires</strong></p>
        @forelse($feedback['comments'] as $comment)
            <blockquote style="margin:6px 0;padding:6px 12px;border-left:3px solid var(--line)">{{ $comment }}</blockquote>
        @empty
            <p class="text-muted">Aucun commentaire écrit.</p>
        @endforelse
    </div>
@else
    <p class="text-sm text-muted">Aucun avis pour le moment.</p>
@endif
