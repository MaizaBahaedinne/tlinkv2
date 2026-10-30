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
                            @if($membership->status === 'pending')
                                <form method="post" action="{{ route('memberships.destroy', $membership) }}" class="mt-2" onsubmit="return confirm('Retirer cette demande d\u2019adhésion ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-xl border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Retirer ma demande</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">Vous n’avez encore aucune adhésion. Faites votre première demande ci-contre.</li>
                    @endforelse
                </ul>

                <div class="mt-8 border-t border-slate-100 pt-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Mes postes par mandat</h2>
                        @if($canEditPositions)
                            <a href="{{ route('admin.mandates.index') }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-tlink-blue hover:bg-slate-50">Gérer les mandats et leur statut</a>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        @if($canEditPositions)
                            Tous les mandats sont listés, y compris les précédents. « Modifier » ouvre l’organigramme du mandat concerné.
                        @else
                            Tous les mandats sont listés, y compris les précédents. Les postes sont attribués dans l’organigramme par les administrateurs de la Fondation : pour en corriger un, contactez-les.
                        @endif
                    </p>

                    <div class="mt-4 space-y-4">
                        @forelse($positionsByMandate as $group)
                            @php
                                $mandate = $group['mandate'];
                                $mandateStatus = $mandate ? (\App\Models\Mandate::STATUSES[$mandate->status] ?? null) : null;
                                $chartUrl = route('orgchart.index', array_filter(['mandate_id' => $mandate?->id]));
                            @endphp
                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-tlink-blue">
                                        {{ $mandate ? 'Mandat '.$mandate->label : 'Hors mandat' }}
                                        @if($mandate && filled($mandate->bureau_name))<span class="text-xs font-normal text-slate-500">· {{ $mandate->bureau_name }}</span>@endif
                                        @if($mandateStatus)<span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $mandate->status === 'current' ? 'bg-emerald-50 text-emerald-700' : ($mandate->status === 'upcoming' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600') }}">{{ $mandateStatus }}</span>@endif
                                    </p>
                                    @if($canEditPositions && $group['positions']->isEmpty())
                                        <a href="{{ $chartUrl }}" class="text-xs font-semibold text-tlink-blue underline">Ouvrir l’organigramme</a>
                                    @endif
                                </div>
                                @if($group['positions']->isEmpty())
                                    <p class="mt-1 text-xs text-slate-400">Aucun poste sur ce mandat.</p>
                                @endif
                                <ul class="mt-2 space-y-2">
                                    @foreach($group['positions'] as $assignment)
                                        @php
                                            $ongoing = $assignment->ends_at === null || $assignment->ends_at->toDateString() >= now()->toDateString();
                                            $detail = collect([$assignment->node?->subtitle, $assignment->person?->job_title])->filter()->unique()->implode(' · ');
                                        @endphp
                                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 px-4 py-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800">{{ $assignment->node?->title ?? 'Poste supprimé' }}</p>
                                                @if($detail !== '')<p class="text-xs text-slate-500">{{ $detail }}</p>@endif
                                                <p class="text-xs text-slate-400">
                                                    {{ $assignment->starts_at?->format('d/m/Y') ?? '—' }} → {{ $assignment->ends_at?->format('d/m/Y') ?? 'aujourd’hui' }}
                                                    · {{ $ongoing ? 'En poste' : 'Terminé' }}
                                                </p>
                                            </div>
                                            @if($canEditPositions)
                                                <a href="{{ $chartUrl }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-tlink-blue hover:bg-slate-50">Modifier le poste</a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @empty
                            <p class="rounded-2xl bg-slate-50 px-4 py-4 text-center text-sm text-slate-500">Aucun mandat n’est encore enregistré.</p>
                        @endforelse
                    </div>
                </div>
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

                    <div>
                        <label for="motivation_letter" class="text-sm font-semibold text-slate-700">Lettre de motivation</label>
                        <textarea id="motivation_letter" name="motivation_letter" rows="7" required minlength="{{ $motivationMin }}" maxlength="{{ $motivationMax }}" placeholder="Présentez-vous et expliquez pourquoi vous souhaitez nous rejoindre." class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue">{{ old('motivation_letter') }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">{{ $motivationMin }} caractères minimum.</p>
                        @error('motivation_letter')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    @if($missingCv !== [])
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-xs text-amber-900">
                            <p class="font-semibold">Votre CV est incomplet.</p>
                            <p class="mt-1">Ajoutez dans votre profil : {{ implode(', ', $missingCv) }}.</p>
                            <a href="{{ route('profile.show') }}" class="mt-2 inline-block font-semibold text-tlink-blue underline">Compléter mon CV</a>
                        </div>
                    @else
                        <p class="rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800">Votre CV est complet : il sera transmis avec votre demande. <a href="{{ route('profile.show') }}" class="font-semibold underline">Le consulter</a></p>
                    @endif
                    @error('cv')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    @error('membership')<p class="text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror

                    <button type="submit" @disabled($missingCv !== []) class="w-full rounded-xl bg-tlink-blue px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">Envoyer ma demande</button>
                    <p class="text-xs text-slate-500">Vous serez prévenu(e) dans vos notifications dès que votre demande sera traitée.</p>
                </form>
            </aside>
        </div>
    </div>
</div>
@endsection
