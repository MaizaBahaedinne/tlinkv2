{{-- En-tête commun des pages de gestion d'un événement : titre et onglets selon les droits. --}}
@php
    $tabs = [
        'overview' => ['Aperçu', 'admin.events.overview', 'view'],
        'registrations' => ['Inscriptions', 'admin.events.registrations', 'registrations'],
        'tickets' => ['Billetterie', 'admin.events.tickets.index', 'tickets'],
        'program' => ['Programme', 'admin.events.program.index', 'program'],
        'speakers' => ['Intervenants', 'admin.events.speakers.index', 'program'],
        'sponsors' => ['Sponsors & partenaires', 'admin.events.sponsors.index', 'sponsors'],
        'payments' => ['Paiements', 'admin.events.payments.index', 'registrations'],
        'transport' => ['Transport', 'admin.events.transport.index', 'transport'],
        'team' => ['Équipe OC', 'admin.events.team.index', 'team'],
    ];
@endphp

<x-admin.page-header :title="$event->title" :subtitle="$subtitle ?? ($tabs[$tab][0] ?? 'Gestion de l’événement')">
    <x-slot:actions>
        <a href="{{ route('member.events.show', $event) }}" class="btn btn-secondary"><x-admin.icon name="eye" :size="16" /> Page publique</a>
        @if($isOrganizer)
            <a href="{{ route('admin.events.show', $event) }}" class="btn btn-secondary"><x-admin.icon name="edit" :size="16" /> Fiche admin</a>
        @endif
    </x-slot:actions>
</x-admin.page-header>

<nav class="tabs" style="margin-bottom:14px" aria-label="Gestion de l’événement">
    @foreach($tabs as $key => [$label, $routeName, $ability])
        @if(in_array($ability, $abilities, true) || ($key === 'transport' && in_array('transport_bus', $abilities, true)))
            <a href="{{ route($routeName, $event) }}" class="tab {{ $tab === $key ? 'is-active' : '' }}">{{ $label }}</a>
        @endif
    @endforeach
</nav>
