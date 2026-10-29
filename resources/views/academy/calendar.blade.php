@extends('layouts.base', ['title' => 'Calendrier | '.$name])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container max-w-3xl space-y-5 py-6">
        <a href="{{ route('academy.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> {{ $name }}</a>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h1 class="font-display text-2xl font-semibold text-slate-900">Calendrier</h1>
            <p class="mt-1 text-sm text-slate-600">Sessions en direct de {{ $name }} et événements de la Fondation Tunivisions.</p>
        </section>

        @forelse($entries as $entry)
            <article class="flex gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
                <div class="w-16 shrink-0 rounded-2xl bg-slate-50 py-2 text-center">
                    <p class="text-xl font-semibold text-slate-900">{{ $entry['at']->format('d') }}</p>
                    <p class="text-[11px] font-semibold uppercase text-slate-500">{{ $entry['at']->translatedFormat('M') }}</p>
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-display text-base font-semibold text-slate-900">{{ $entry['title'] }}</h2>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $entry['kind'] === 'session' ? 'bg-rose-50 text-tlink-blue' : 'bg-slate-100 text-slate-600' }}">{{ $entry['kind'] === 'session' ? 'Academy' : 'Fondation' }}</span>
                    </div>
                    <p class="text-sm text-slate-600">{{ $entry['at']->translatedFormat('l H:i') }}@if($entry['ends_at']) – {{ $entry['ends_at']->format('H:i') }}@endif @if($entry['location']) · {{ $entry['location'] }} @endif</p>
                    @if($entry['course'])<p class="text-xs text-slate-500">Cours : {{ $entry['course']->title }}</p>@endif
                    @if($entry['description'])<p class="whitespace-pre-line text-sm text-slate-600">{{ $entry['description'] }}</p>@endif
                    @if($entry['meeting_url'])
                        <a href="{{ $entry['meeting_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-block text-sm font-semibold text-tlink-blue hover:underline">Rejoindre la session</a>
                    @elseif($entry['kind'] === 'session' && $entry['course'])
                        <p class="text-xs text-slate-500">Le lien de connexion est réservé aux membres inscrits au cours.</p>
                    @endif
                    @if($entry['url'])
                        <a href="{{ $entry['url'] }}" class="inline-block text-sm font-semibold text-tlink-blue hover:underline">Voir l’événement</a>
                    @endif
                </div>
            </article>
        @empty
            <p class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Aucune session ni aucun événement à venir pour le moment.</p>
        @endforelse
    </main>
</div>
@endsection
