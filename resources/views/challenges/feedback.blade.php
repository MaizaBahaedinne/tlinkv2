@extends('layouts.base', ['title' => 'Votre avis | T.Link'])

@php
    $questions = [
        'feedback_overall' => 'Satisfaction globale',
        'feedback_organization' => 'Organisation (accueil, horaires, logistique)',
        'feedback_content' => 'Contenu de l’activité',
    ];
@endphp

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container mx-auto max-w-xl space-y-5 py-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h1 class="font-display text-2xl font-semibold text-slate-900">Votre avis</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $activity->title }} · {{ $activity->club->title }}</p>
            <p class="mt-3 rounded-2xl bg-sky-50 px-4 py-3 text-sm text-sky-900">L’envoi de ce formulaire valide votre présence. Vos notes restent anonymes pour le club.</p>

            <form method="post" action="{{ route('activities.feedback', [$activity, $token]) }}" class="mt-5 space-y-5">
                @csrf

                @foreach($questions as $name => $label)
                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-900">{{ $label }} <span class="text-rose-600">*</span></legend>
                        <div class="mt-2 flex gap-2">
                            @foreach(\App\Models\ActivityReport::RATINGS as $score => $rating)
                                <label class="flex-1 cursor-pointer text-center">
                                    <input type="radio" name="{{ $name }}" value="{{ $score }}" class="peer sr-only" @checked((string) old($name) === (string) $score) required>
                                    <span class="block rounded-xl border border-slate-300 px-2 py-2 text-sm font-semibold peer-checked:border-tlink-blue peer-checked:bg-tlink-blue peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-tlink-blue">{{ $score }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-slate-500">1 = {{ \App\Models\ActivityReport::RATINGS[1] }} · 5 = {{ \App\Models\ActivityReport::RATINGS[5] }}</p>
                        @error($name)<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                    </fieldset>
                @endforeach

                <div>
                    <label for="feedback_comment" class="text-sm font-semibold text-slate-900">Commentaire (facultatif)</label>
                    <textarea id="feedback_comment" name="feedback_comment" rows="4" maxlength="1000" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Ce qui vous a plu, ce qui pourrait être amélioré…">{{ old('feedback_comment') }}</textarea>
                    @error('feedback_comment')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white hover:opacity-90"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Envoyer mon avis et valider ma présence</button>
            </form>
        </section>
    </main>
</div>
@endsection
