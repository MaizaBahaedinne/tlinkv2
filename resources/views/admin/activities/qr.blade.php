@extends('admin.layouts.app')

@section('title', 'QR code de présence')
@section('section', 'Défis')

@section('content')
    <x-admin.page-header :title="'QR code — '.$activity->title" subtitle="Affichez ou imprimez ce QR code sur place : chaque participant inscrit le scanne avec son smartphone pour valider sa présence.">
        <x-slot:actions>
            <a href="{{ route('admin.activities.show', $activity) }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><x-admin.icon name="file-text" :size="16" /> Imprimer</button>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="card" style="max-width:520px;margin:0 auto;text-align:center">
        <div class="card-body">
            <h2 style="margin:0 0 4px">{{ $activity->title }}</h2>
            <p class="text-sm text-muted" style="margin:0 0 14px">{{ $activity->starts_at->format('d/m/Y H:i') }}{{ $activity->location ? ' · '.$activity->location : '' }}</p>
            <div data-qr="{{ $activity->checkinUrl() }}" style="max-width:380px;margin:0 auto">QR code en cours de génération…</div>
            <p class="text-sm" style="margin-top:14px">Scannez pour valider votre présence <br><span class="text-muted">(inscription préalable obligatoire)</span></p>
            <p class="text-sm text-muted" style="word-break:break-all;margin-top:10px">{{ $activity->checkinUrl() }}</p>
            <p class="text-sm" style="margin-top:10px">
                Validation ouverte du <strong>{{ $activity->starts_at->copy()->subHour()->format('d/m/Y H:i') }}</strong> au <strong>{{ $activity->endsAt()->copy()->addHours(3)->format('d/m/Y H:i') }}</strong>.
                @if($activity->status !== 'published')<br><strong>L’activité n’est pas publiée : le QR code est sans effet.</strong>@endif
            </p>
        </div>
    </article>
@endsection

@push('scripts')
    @vite(['resources/js/qr.js'])
@endpush
