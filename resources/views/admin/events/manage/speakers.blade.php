@extends('admin.layouts.app')

@section('title', 'Intervenants — '.$event->title)
@section('section', 'Événements')

@section('content')
    @include('admin.events.manage._header')

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Intervenants</h2>
                <div class="sub">Speakers, formateurs, invités d’honneur… Tunivisions ou externes. Rattachez-les aux étapes depuis l’onglet Programme.</div>
            </div>
        </div>

        @if($speakers->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Intervenant</th><th>Rôle</th><th class="hide-mobile">Origine</th><th class="hide-mobile">Étapes</th><th></th></tr></thead>
                    <tbody>
                        @foreach($speakers as $speaker)
                            <tr>
                                <td>
                                    <div class="cell-user">
                                        <x-admin.avatar :name="$speaker->name" :size="34" />
                                        <div>
                                            <div class="cell-main">{{ $speaker->name }}</div>
                                            <div class="cell-sub">{{ collect([$speaker->job_title, $speaker->organization])->filter()->implode(' · ') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $speaker->kindLabel() }}</td>
                                <td class="hide-mobile">{{ $speaker->originLabel() }}</td>
                                <td class="hide-mobile text-sm">{{ $speaker->sessions->pluck('title')->implode(', ') ?: '—' }}</td>
                                <td class="text-right nowrap">
                                    <details>
                                        <summary class="btn btn-ghost btn-sm">Modifier</summary>
                                        <div style="padding:12px 0;text-align:left">
                                            @include('admin.events.manage._speaker_form', ['speaker' => $speaker, 'action' => route('admin.events.speakers.update', [$event, $speaker])])
                                            <form method="post" action="{{ route('admin.events.speakers.destroy', [$event, $speaker]) }}" onsubmit="return confirm('Supprimer cet intervenant ?')">
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
            <x-admin.empty icon="users" title="Aucun intervenant" text="Ajoutez les speakers, formateurs et invités d’honneur." />
        @endif
    </article>

    <article class="card card-flush">
        <div class="card-head"><h2>Nouvel intervenant</h2></div>
        <div class="card-body">
            @include('admin.events.manage._speaker_form', ['speaker' => new \App\Models\EventSpeaker(['kind' => 'speaker', 'origin' => 'external']), 'action' => route('admin.events.speakers.store', $event)])
        </div>
    </article>
@endsection
