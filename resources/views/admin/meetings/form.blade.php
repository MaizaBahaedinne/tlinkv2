@extends('admin.layouts.app')

@section('title', $meeting->exists ? 'Modifier la réunion' : 'Nouvelle réunion')
@section('section', 'Gouvernance')

@section('content')
    <x-admin.page-header :title="$meeting->exists ? 'Modifier « '.$meeting->title.' »' : 'Nouvelle réunion'" subtitle="Les invités sont prévenus automatiquement si la date ou le lieu change après l’envoi des invitations.">
        <x-slot:actions>
            <a href="{{ $meeting->exists ? route('admin.meetings.show', $meeting) : route('admin.meetings.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ $meeting->exists ? route('admin.meetings.update', $meeting) : route('admin.meetings.store') }}" class="card">
        @csrf
        @if($meeting->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="form-grid">
                <x-admin.select name="club_id" label="Club" :options="$clubOptions" :value="$meeting->club_id" :required="! $canBeFoundation" :placeholder="$canBeFoundation ? 'Fondation (sans club)' : 'Choisir un club'" />
                <x-admin.input name="title" label="Intitulé" :value="$meeting->title" :required="true" maxlength="190" />
                <x-admin.input name="starts_at" type="datetime-local" label="Date et heure" :value="optional($meeting->starts_at)->format('Y-m-d\TH:i')" :required="true" />
                <x-admin.input name="duration_minutes" type="number" label="Durée (minutes)" :value="$meeting->duration_minutes" :required="true" min="5" max="1440" />
                <x-admin.input name="location" label="Lieu" :value="$meeting->location" maxlength="190" placeholder="Salle, adresse…" />
                <x-admin.input name="online_url" type="url" label="Lien de visioconférence" :value="$meeting->online_url" maxlength="255" placeholder="https://" />
                <x-admin.textarea name="subject" label="Sujet de la réunion" :value="$meeting->subject" rows="3" maxlength="3000" />
                <x-admin.textarea name="guests" label="Invités externes (sponsors, partenaires… non inscrits sur T.Link)" :value="$meeting->guests" rows="2" maxlength="2000" />
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><x-admin.icon name="check" :size="16" /> Enregistrer</button>
        </div>
    </form>
@endsection
