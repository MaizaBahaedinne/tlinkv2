@extends('admin.layouts.app')

@section('title', 'Billetterie — '.$event->title)
@section('section', 'Événements')

@section('content')
    @include('admin.events.manage._header')

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Releases d’inscription</h2>
                <div class="sub">Chaque release a son prix, son quota et sa fenêtre d’ouverture. Sans release active, le prix de l’événement s’applique.</div>
            </div>
        </div>

        @if($releases->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Release</th><th>Prix</th><th>Places</th><th class="hide-mobile">Fenêtre</th><th>État</th><th></th></tr></thead>
                    <tbody>
                        @foreach($releases as $release)
                            <tr>
                                <td><div class="cell-main">{{ $release->name }}</div><div class="cell-sub">{{ $release->description }}</div></td>
                                <td class="nowrap">{{ $release->priceLabel() }}</td>
                                <td class="nowrap">{{ $release->seats }}{{ $release->quantity ? ' / '.$release->quantity : '' }}</td>
                                <td class="hide-mobile text-sm text-muted">{{ $release->opens_at?->format('d/m/Y H:i') ?? '…' }} → {{ $release->closes_at?->format('d/m/Y H:i') ?? '…' }}</td>
                                <td>
                                    @if(! $release->is_active)<x-admin.badge status="inactive" />
                                    @elseif($release->isOpen())<x-admin.badge status="active" label="Ouverte" />
                                    @else<x-admin.badge status="pending" label="Hors fenêtre" />@endif
                                </td>
                                <td class="text-right nowrap">
                                    <details>
                                        <summary class="btn btn-ghost btn-sm">Modifier</summary>
                                        <div style="padding:12px 0">
                                            @include('admin.events.manage._release_form', ['release' => $release, 'action' => route('admin.events.tickets.update', [$event, $release])])
                                            <form method="post" action="{{ route('admin.events.tickets.destroy', [$event, $release]) }}" onsubmit="return confirm('Supprimer cette release ?')">
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
            <x-admin.empty icon="calendar" title="Aucune release" text="Ajoutez une première release pour ouvrir les inscriptions avec un tarif dédié." />
        @endif
    </article>

    <article class="card card-flush">
        <div class="card-head"><h2>Nouvelle release</h2></div>
        <div class="card-body">
            @include('admin.events.manage._release_form', ['release' => new \App\Models\EventRelease(['price' => 0]), 'action' => route('admin.events.tickets.store', $event)])
        </div>
    </article>
@endsection
