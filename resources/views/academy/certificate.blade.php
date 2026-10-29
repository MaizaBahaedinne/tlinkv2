@extends('layouts.base', ['title' => 'Certificat '.$enrollment->certificate_code.' | '.$name])

@section('content')
<div class="min-h-screen bg-tlink-sand px-4 py-10 text-slate-800">
    <main class="mx-auto max-w-3xl space-y-4">
        <section class="rounded-3xl border-4 border-double border-amber-300 bg-white p-8 text-center shadow-soft sm:p-12">
            <div class="flex items-center justify-between gap-6">
                <div class="flex h-16 items-center" data-certificate-logo="academy">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $name }}" class="max-h-16 max-w-[10rem] object-contain">
                    @else
                        <p class="text-xs font-bold uppercase tracking-[0.25em] text-slate-500">{{ $name }}</p>
                    @endif
                </div>
                <div class="flex h-16 items-center" data-certificate-logo="foundation">
                    <picture>
                        <source srcset="{{ asset('logoFondation.webp') }}" type="image/webp">
                        <img src="{{ asset('logoFondation.png') }}" alt="Fondation Tunivisions" class="max-h-16 max-w-[10rem] object-contain">
                    </picture>
                </div>
            </div>
            <h1 class="mt-8 font-display text-3xl font-semibold text-slate-900">Certificat de réussite</h1>
            <p class="mt-6 text-sm text-slate-600">décerné à</p>
            <p class="mt-1 font-display text-2xl font-semibold text-tlink-blue">{{ $enrollment->user?->name }}</p>
            <p class="mt-4 text-sm text-slate-600">pour avoir terminé le cours</p>
            <p class="mt-1 font-display text-xl font-semibold text-slate-900">{{ $enrollment->course?->title }}</p>
            <p class="mt-6 text-sm text-slate-600">le {{ $enrollment->completed_at->translatedFormat('d F Y') }}</p>
            <div class="mt-12 grid grid-cols-2 gap-8 text-center">
                @foreach([['manager', $managerSignatureUrl], ['president', $presidentSignatureUrl]] as [$who, $signatureUrl])
                    <div data-certificate-signature="{{ $who }}">
                        <div class="flex h-16 items-end justify-center">
                            @if($signatureUrl)
                                <img src="{{ $signatureUrl }}" alt="Signature" class="max-h-16 max-w-full object-contain">
                            @endif
                        </div>
                        <div class="mt-1 border-t border-slate-400 pt-2">
                            @if($settings[$who.'_name'] !== '')
                                <p class="text-sm font-semibold text-slate-900">{{ $settings[$who.'_name'] }}</p>
                            @endif
                            <p class="text-xs text-slate-600">{{ $settings[$who.'_title'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-10 text-xs text-slate-500">Code de vérification : <strong class="font-mono">{{ $enrollment->certificate_code }}</strong></p>
        </section>
        <p class="text-center text-xs text-slate-500 print:hidden">
            <button type="button" onclick="window.print()" class="font-semibold text-tlink-blue hover:underline">Imprimer ou enregistrer en PDF</button>
            @auth · <a href="{{ route('academy.index') }}" class="font-semibold text-tlink-blue hover:underline">Retour à {{ $name }}</a>@endauth
        </p>
    </main>
</div>
@endsection
