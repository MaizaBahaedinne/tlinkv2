@props(['status', 'label' => null])
@php
    $map = [
        'active' => ['Actif', 'success'],
        'inactive' => ['Inactif', 'neutral'],
        'draft' => ['Brouillon', 'neutral'],
        'archived' => ['Archivé', 'neutral'],
        'pending' => ['En attente', 'warning'],
        'approved' => ['Approuvé', 'success'],
        'rejected' => ['Rejeté', 'danger'],
        'suspended' => ['Suspendu', 'danger'],
        'cancelled' => ['Annulé', 'neutral'],
        'confirmed' => ['Confirmé', 'success'],
        'waitlisted' => ['Liste d\'attente', 'warning'],
        'attended' => ['Présent', 'success'],
        'absent' => ['Absent', 'danger'],
        'published' => ['Publié', 'success'],
        'completed' => ['Terminé', 'info'],
        'reviewed' => ['Traité', 'success'],
        'ended' => ['Terminé', 'neutral'],
        'todo' => ['À faire', 'neutral'],
        'in_progress' => ['En cours', 'info'],
        'done' => ['Terminé', 'success'],
        'blocked' => ['Bloqué', 'danger'],
        'university' => ['Université', 'info'],
        'high_school' => ['Lycée', 'info'],
        'other' => ['Autre', 'neutral'],
    ];
    [$defaultLabel, $tone] = $map[$status] ?? [ucfirst(str_replace('_', ' ', (string) $status)), 'neutral'];
@endphp
<span class="badge badge-{{ $tone }}">{{ $label ?? $defaultLabel }}</span>
