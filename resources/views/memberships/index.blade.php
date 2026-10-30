@extends('layouts.base', ['title' => 'Mes adhésions | T.Link'])

@section('content')
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <div>
            <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mes adhésions</h1>
            <p class="max-w-2xl text-sm text-slate-600">Suivez vos adhésions à la Fondation, à ses entités et à ses clubs, et demandez à en rejoindre une nouvelle. Chaque demande est examinée par l’équipe.</p>
        </div>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <div class="grid gap-5 lg:grid-cols-3">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft lg:col-span-2">
                <h2 class="font-display text-lg font-semibold text-slate-900">Mes demandes et adhésions</h2>

                <ul class="mt-4 space-y-3">
                    @forelse($memberships as $membership)
                        @php
                            [$statusLabel, $statusClass] = $statuses[$membership->status] ?? [$membership->status, 'bg-slate-100 text-slate-600'];
                        @endphp
                        <li class="rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-800">{{ $membership->node?->title ?? (\App\Support\OrgScopes::label($membership->scope) ?: 'Fondation') }}</p>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ \App\Support\OrgScopes::label($membership->scope) }}
                                · demandée le {{ $membership->created_at->format('d/m/Y') }}
                                @if($membership->status === 'approved' && $membership->approved_at) · validée le {{ $membership->approved_at->format('d/m/Y') }}@endif
                            </p>
                            @if($membership->status === 'rejected' && filled($membership->rejection_reason))
                                <p class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-800">Motif : {{ $membership->rejection_reason }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Vous n’avez encore aucune adhésion. Faites votre première demande ci-contre.</li>
                    @endforelse
                </ul>
            </section>

            <aside>
                <form method="post" action="{{ route('memberships.store') }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    @csrf
                    <h2 class="font-display text-lg font-semibold text-slate-900">Demander une adhésion</h2>

                    <div>
                        <label for="target" class="text-sm font-semibold text-slate-700">Où souhaitez-vous adhérer ?</label>
                        <select id="target" name="target" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue">
                            <option value="">Choisir…</option>
                            <optgroup label="Entités de la Fondation">
                                @foreach($scopes as $slug => $label)
                                    <option value="scope:{{ $slug }}" @selected(old('target') === 'scope:'.$slug)>{{ $label }}</option>
                                @endforeach
                            </optgroup>
                            @foreach($clubs as $slug => $list)
                                <optgroup label="Clubs · {{ $scopes[$slug] ?? $slug }}">
                                    @foreach($list as $club)
                                        <option value="club:{{ $club->id }}" @selected(old('target') === 'club:'.$club->id)>{{ $club->title }}{{ filled($club->subtitle) ? ' — '.$club->subtitle : '' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('target')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">Envoyer ma demande</button>
                    <p class="text-xs text-slate-500">Vous serez prévenu(e) dans vos notifications dès que votre demande sera traitée.</p>
                </form>
            </aside>
        </div>
    </div>
</div>
@endsection
