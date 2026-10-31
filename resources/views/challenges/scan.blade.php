@extends('layouts.base', ['title' => 'Scanner un QR code | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <main class="app-container mx-auto max-w-xl space-y-5 py-6">
        <a href="{{ route('challenges.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> Retour aux défis</a>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft" data-scanner>
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-sky-100 text-sky-600"><i class="fa-solid fa-qrcode text-xl" aria-hidden="true"></i></span>
                <div>
                    <h1 class="font-display text-2xl font-semibold text-slate-900">Scanner un QR code</h1>
                    <p class="text-sm text-slate-600">Validez votre présence à une activité de club.</p>
                </div>
            </div>

            <ol class="mt-5 list-decimal space-y-1 rounded-2xl bg-slate-50 p-4 ps-9 text-sm text-slate-700">
                <li>Soyez inscrit à l’activité.</li>
                <li>Ouvrez la caméra et visez le QR code affiché par les organisateurs.</li>
                <li>Remplissez le formulaire d’avis : l’envoi valide votre participation.</li>
            </ol>

            <div data-scanner-frame class="relative mt-5 hidden overflow-hidden rounded-2xl bg-black">
                <video data-scanner-video class="aspect-square w-full object-cover" playsinline muted></video>
                <div class="pointer-events-none absolute inset-8 rounded-2xl border-4 border-white/80"></div>
            </div>

            <p data-scanner-message class="mt-4 text-sm text-slate-600" role="status" aria-live="polite"></p>

            <button type="button" data-scanner-start class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-tlink-blue px-4 py-3 text-sm font-semibold text-white hover:opacity-90">
                <i class="fa-solid fa-camera" aria-hidden="true"></i> Ouvrir la caméra
            </button>
        </section>
    </main>
</div>
@vite(['resources/js/scan.js'])
@endsection
