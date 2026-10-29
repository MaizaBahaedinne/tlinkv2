@extends('layouts.base', ['title' => 'Mentorat | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mentorat</h1>
                <p class="max-w-2xl text-sm text-slate-600">Une demande, un incident, un problème personnel ou de votre collectif (club, projet, chapter…) ? Remontez-le : un manager mentor le qualifie puis un mentor vous accompagne.</p>
            </div>
            <a href="{{ route('mentoring.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-plus" aria-hidden="true"></i> Nouvelle demande</a>
        </div>

        @include('mentoring.partials.tabs')

        @include('mentoring.partials.ticket-list', ['tickets' => $tickets, 'showMentor' => true, 'empty' => 'Vous n’avez encore remonté aucune demande.'])
    </div>
</div>
@endsection
