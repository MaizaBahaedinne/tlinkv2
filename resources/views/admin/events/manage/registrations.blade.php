@extends('admin.layouts.app')

@section('title', 'Inscriptions — '.$event->title)
@section('section', 'Événements')

@section('content')
    @include('admin.events.manage._header')

    <article class="card card-flush">
        <div class="card-head">
            <div>
                <h2>Inscriptions</h2>
                <div class="sub">{{ $registrations->count() }} inscrit(s){{ $event->capacity ? ' sur '.$event->capacity.' places' : '' }}</div>
            </div>
        </div>

        @if($registrations->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Participant</th><th class="hide-mobile">Release</th><th>Montant dû</th><th>Paiement</th><th>Statut</th></tr></thead>
                    <tbody>
                        @foreach($registrations as $registration)
                            <tr>
                                <td>
                                    <div class="cell-main">{{ $registration->user?->name ?? 'Utilisateur supprimé' }}</div>
                                    <div class="cell-sub">{{ $registration->user?->email }}</div>
                                </td>
                                <td class="hide-mobile">{{ $registration->release?->name ?? '—' }}</td>
                                <td class="nowrap">{{ (float) $registration->amount_due > 0 ? number_format((float) $registration->amount_due, 2, ',', ' ').' TND' : 'Gratuit' }}</td>
                                <td>@include('admin.events.manage._payment_badge', ['status' => $registration->payment_status])</td>
                                <td>
                                    <form method="post" action="{{ route('admin.events.registrations.status', [$event, $registration]) }}" class="inline-form">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" aria-label="Statut de l’inscription" onchange="this.form.submit()">
                                            @foreach($statuses as $code => $label)
                                                <option value="{{ $code }}" @selected($registration->status === $code)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button type="submit" class="btn btn-secondary btn-sm">OK</button></noscript>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty icon="users" title="Aucune inscription" text="Personne ne s’est encore inscrit à cet événement." />
        @endif
    </article>
@endsection
