@extends('layouts.base', ['title' => 'Mentors | Mentorat | T.Link'])

@section('content')
@php
    $input = 'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue';
    $managers = count(\App\Support\Mentoring\Mentoring::managerIds());
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mentors</h1>
            <p class="text-sm text-slate-600">Le manager mentor reçoit, qualifie et affecte les tickets ; les mentors accompagnent les membres et rédigent le REX.</p>
        </div>

        @include('mentoring.partials.tabs')

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
            </div>
        @endif

        @if($managers === 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">Aucun manager mentor actif : les nouveaux tickets ne sont reçus par personne. Ajoutez-en un ci-dessous.</div>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
            <h2 class="font-display text-lg font-semibold text-slate-900">Ajouter un membre</h2>
            <form method="get" action="{{ route('mentoring.mentors') }}" class="mt-3 flex flex-wrap gap-3">
                <label for="m-q" class="sr-only">Rechercher un membre</label>
                <input id="m-q" type="search" name="q" value="{{ $term }}" maxlength="80" placeholder="Nom du membre (2 lettres minimum)" class="min-w-[14rem] flex-1 {{ $input }}">
                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Rechercher</button>
            </form>

            @if($term !== '')
                <ul class="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-100">
                    @forelse($found as $member)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <span class="text-sm font-semibold text-slate-800">{{ $member->name }}</span>
                            <form method="post" action="{{ route('mentoring.mentors.store') }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $member->id }}">
                                <label for="s-{{ $member->id }}" class="sr-only">Spécialités</label>
                                <input id="s-{{ $member->id }}" type="text" name="specialties" maxlength="255" placeholder="Spécialités (facultatif)" class="w-52 {{ $input }}">
                                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="manager" value="1" class="accent-tlink-blue"> Manager</label>
                                <button type="submit" class="rounded-xl bg-tlink-blue px-3 py-2 text-sm font-semibold text-white hover:opacity-90">Ajouter</button>
                            </form>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-slate-500">Aucun membre trouvé (ou déjà dans l’annuaire).</li>
                    @endforelse
                </ul>
            @endif
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-soft">
            <ul class="divide-y divide-slate-100">
                @forelse($rows as $row)
                    <li class="px-4 py-4 sm:px-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-slate-900">{{ $row->user?->name ?? 'Membre supprimé' }}</span>
                            @if($row->is_manager)@include('mentoring.partials.badge', ['label' => 'Manager mentor', 'tone' => 'violet'])@endif
                            @if(! $row->is_active)@include('mentoring.partials.badge', ['label' => 'Inactif', 'tone' => 'neutral'])@endif
                            <span class="text-xs text-slate-500">{{ $workload[$row->user_id] ?? 0 }} ticket(s) en cours</span>
                        </div>
                        <form method="post" action="{{ route('mentoring.mentors.update', $row) }}" class="mt-3 flex flex-wrap items-center gap-3">
                            @csrf
                            @method('PATCH')
                            <label for="sp-{{ $row->id }}" class="sr-only">Spécialités</label>
                            <input id="sp-{{ $row->id }}" type="text" name="specialties" value="{{ $row->specialties }}" maxlength="255" placeholder="Spécialités" class="min-w-[12rem] flex-1 {{ $input }}">
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="manager" value="1" class="accent-tlink-blue" @checked($row->is_manager)> Manager</label>
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="active" value="1" class="accent-tlink-blue" @checked($row->is_active)> Actif</label>
                            <button type="submit" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50">Enregistrer</button>
                        </form>
                        <form method="post" action="{{ route('mentoring.mentors.destroy', $row) }}" class="mt-2" onsubmit="return confirm('Retirer ce membre de l’annuaire des mentors ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Retirer de l’annuaire</button>
                        </form>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-slate-500">L’annuaire est vide. Recherchez un membre pour le nommer mentor ou manager mentor.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
