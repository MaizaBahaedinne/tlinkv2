@extends('layouts.base', ['title' => 'Charte des réseaux sociaux | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-24 text-slate-800 lg:pb-12">
    @include('partials.app-header')

    <div class="app-container max-w-3xl space-y-6 py-6">
        <a href="{{ route('marketing-access.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline">← Mes accès réseaux sociaux</a>

        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">{{ $charter['title'] }}</h1>
            <p class="text-sm text-slate-600">Version {{ $charter['version'] }} · pages demandées : {{ $accessRequest->platformLabels() }}</p>
        </div>

        <article class="whitespace-pre-line rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-relaxed text-slate-700 shadow-sm">{{ $charter['body'] }}</article>

        @if($signed)
            <p class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Signée par {{ $accessRequest->charter_signed_name }} le {{ $accessRequest->charter_signed_at->format('d/m/Y à H:i') }}.</p>
        @elseif($accessRequest->status === 'approved')
            <form method="post" action="{{ route('marketing-access.sign', $accessRequest) }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="accept" value="1" class="mt-1" required>
                    <span>J’ai lu la charte d’usage des réseaux sociaux de {{ $accessRequest->entityLabel() }} et je m’engage à la respecter.</span>
                </label>
                @error('accept')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                <div>
                    <label for="signed_name" class="block text-sm font-semibold">Nom complet (signature)</label>
                    <input id="signed_name" name="signed_name" type="text" value="{{ old('signed_name', auth()->user()->name) }}" maxlength="160" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @error('signed_name')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="rounded-full bg-tlink-blue px-5 py-2 text-sm font-semibold text-white hover:opacity-90">Signer et accéder aux comptes</button>
            </form>
        @else
            <p class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">Cette charte n’est pas à signer actuellement ({{ $accessRequest->statusLabel() }}).</p>
        @endif
    </div>
</div>
@endsection
