@extends('admin.layouts.app')

@section('title', 'Programme — '.$event->title)
@section('section', 'Événements')

@section('content')
    @include('admin.events.manage._header')

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Programme</h2>
                <div class="sub">Chaque étape a son lieu (dans le lieu de l’événement ou externe), ses intervenants et une inscription facultative.</div>
            </div>
        </div>

        @if($sessions->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Quand</th><th>Étape</th><th class="hide-mobile">Lieu</th><th class="hide-mobile">Intervenants</th><th></th></tr></thead>
                    <tbody>
                        @foreach($sessions as $session)
                            <tr>
                                <td class="nowrap text-sm">{{ $session->starts_at->format('d/m H:i') }}{{ $session->ends_at ? ' → '.$session->ends_at->format('H:i') : '' }}</td>
                                <td>
                                    <div class="cell-main">{{ $session->title }}</div>
                                    <div class="cell-sub">{{ $session->typeLabel() }}@if($session->requires_registration) · inscription requise ({{ $session->seats }}{{ $session->capacity ? ' / '.$session->capacity : '' }})@endif</div>
                                </td>
                                <td class="hide-mobile text-sm">{{ $session->setRelation('event', $event)->venueLabel() ?? '—' }}{{ $session->venue_mode === 'external' ? ' (externe)' : '' }}</td>
                                <td class="hide-mobile text-sm">{{ $session->speakers->pluck('name')->implode(', ') ?: '—' }}</td>
                                <td class="text-right nowrap">
                                    <details>
                                        <summary class="btn btn-ghost btn-sm">Modifier</summary>
                                        <div style="padding:12px 0;text-align:left">
                                            @include('admin.events.manage._session_form', ['session' => $session, 'action' => route('admin.events.program.update', [$event, $session])])
                                            <form method="post" action="{{ route('admin.events.program.destroy', [$event, $session]) }}" onsubmit="return confirm('Supprimer cette étape ?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Supprimer</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-admin.empty icon="calendar" title="Programme vide" text="Ajoutez la cérémonie d’ouverture, les panels, ateliers, soirée…" />
        @endif
    </article>

    <article class="card card-flush">
        <div class="card-head"><h2>Nouvelle étape</h2></div>
        <div class="card-body">
            @include('admin.events.manage._session_form', ['session' => new \App\Models\EventSession(['venue_mode' => 'event', 'type' => 'other']), 'action' => route('admin.events.program.store', $event)])
        </div>
    </article>
@endsection
