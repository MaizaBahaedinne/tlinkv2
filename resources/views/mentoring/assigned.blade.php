@extends('layouts.base', ['title' => 'Mes accompagnements | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mes accompagnements</h1>
            <p class="text-sm text-slate-600">Les tickets qui vous sont confiés : démarrez l’accompagnement, échangez avec le demandeur et rédigez le REX.</p>
        </div>

        @include('mentoring.partials.tabs')

        @include('mentoring.partials.ticket-list', ['tickets' => $tickets, 'showRequester' => true, 'empty' => 'Aucun ticket ne vous est confié pour le moment.'])
    </div>
</div>
@endsection
