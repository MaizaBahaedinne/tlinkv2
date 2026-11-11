<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="font-display text-2xl font-semibold text-tlink-blue">Mes adhésions</h1>
        <p class="max-w-2xl text-sm text-slate-600">Votre parcours dans la Fondation, mandat après mandat : adhésions, postes et participations. Chaque demande est examinée par l’équipe.</p>
    </div>
    <button type="button" data-open-request class="inline-flex items-center gap-2 rounded-xl bg-tlink-blue px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-90"><i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> Demander une adhésion</button>
</div>

@if(session('status'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
@endif

@if($errors->has('membership') && old('target') === null)
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert">{{ $errors->first('membership') }}</div>
@endif

@if($joinedMandate)
    <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">Vous avez rejoint le réseau au mandat <strong>{{ $joinedMandate->label }}</strong>. Déclarez votre participation aux mandats suivants ; les mandats antérieurs à votre arrivée sont grisés.</div>
@endif

<div class="grid gap-3 sm:grid-cols-3">
    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-soft">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Adhésions actives</p>
        <p class="mt-1 font-display text-3xl font-semibold text-emerald-600">{{ $stats['active'] }}</p>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-soft">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Demandes en attente</p>
        <p class="mt-1 font-display text-3xl font-semibold text-amber-600">{{ $stats['pending'] }}</p>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-soft">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Postes en cours</p>
        <p class="mt-1 font-display text-3xl font-semibold text-tlink-blue">{{ $stats['positions'] }}</p>
    </div>
</div>

@if($memberships->isEmpty())
    <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-6 text-center text-sm text-slate-500">Vous n’avez encore aucune adhésion. <button type="button" data-open-request class="font-semibold text-tlink-blue underline">Faites votre première demande</button>.</div>
@endif

<section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft sm:p-6" aria-labelledby="journey-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="journey-title" class="font-display text-lg font-semibold text-slate-900">Mon parcours par mandat</h2>
        @if($canEditPositions)
            <a href="{{ route('admin.mandates.index') }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-tlink-blue hover:bg-slate-50">Gérer les mandats et leur statut</a>
        @endif
    </div>
    <p class="mt-1 text-xs text-slate-500">
        Tous les mandats sont listés, y compris les précédents. Pour un ancien mandat, déclarez votre participation : un poste de membre est accepté directement, un poste de responsabilité (président, vice-président, assistant…) doit être approuvé par un administrateur.
        @error('participation')<span class="mt-1 block font-semibold text-rose-700">{{ $message }}</span>@enderror
    </p>

    @if($positionsByMandate->isEmpty())
        <p class="mt-5 rounded-2xl bg-slate-50 px-4 py-4 text-center text-sm text-slate-500">Aucun mandat n’est encore enregistré.</p>
    @else
        <ol class="relative mt-6 ml-3 space-y-5 border-l-2 border-slate-200 pl-6 sm:ml-4 sm:pl-8" aria-label="Chronologie des mandats">
            @foreach($positionsByMandate as $group)
                @php
                    $mandate = $group['mandate'];
                    $mandateStatus = $mandate ? (\App\Models\Mandate::STATUSES[$mandate->status] ?? null) : null;
                    $isPast = $mandate !== null && $mandate->status === 'closed';
                    $isCurrent = $mandate !== null && $mandate->status === 'current';
                    $beforeArrival = $mandate !== null && $joinedMandate !== null && $mandate->precedes($joinedMandate);
                    $dot = match ($mandate?->status) {
                        'current' => 'bg-emerald-500',
                        'upcoming' => 'bg-sky-500',
                        default => 'bg-slate-400',
                    };
                    // Une adhésion liée à un poste occupé n'a qu'une ligne : le poste y est fusionné.
                    $heldPosts = $group['positions']->keyBy('node_id');
                    $mergedNodes = $group['memberships']->merge($group['carried'])
                        ->filter(fn ($m) => $m->status === 'approved' && $m->position_id !== null && $heldPosts->has($m->position_id))
                        ->pluck('position_id')->all();
                    $empty = $group['memberships']->isEmpty() && $group['carried']->isEmpty() && $group['participations']->isEmpty() && $group['positions']->isEmpty();
                @endphp
                <li class="relative">
                    <span class="absolute top-5 -left-[35px] h-4 w-4 rounded-full ring-4 ring-white sm:-left-[43px] {{ $dot }}" aria-hidden="true"></span>
                    <article @class(['rounded-2xl border bg-slate-50/60 p-4', 'border-emerald-200 ring-1 ring-emerald-100' => $isCurrent, 'border-slate-100' => ! $isCurrent, 'opacity-50' => $beforeArrival])
                             @if($beforeArrival) aria-disabled="true" data-before-arrival @endif>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-tlink-blue">
                                {{ $mandate ? 'Mandat '.$mandate->label : 'Hors mandat' }}
                                @if($mandate && filled($mandate->bureau_name))<span class="text-xs font-normal text-slate-500">· {{ $mandate->bureau_name }}</span>@endif
                                @if($mandateStatus)<span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $isCurrent ? 'bg-emerald-50 text-emerald-700' : ($mandate->status === 'upcoming' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600') }}">{{ $mandateStatus }}</span>@endif
                            </p>
                            @if($isPast && $beforeArrival)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Avant mon arrivée</span>
                            @elseif($isPast)
                                <button type="button" data-open-past data-mandate-id="{{ $mandate->id }}" data-mandate-label="{{ $mandate->label }}" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-tlink-blue hover:bg-slate-50">Ajouter ma participation</button>
                            @endif
                        </div>

                        @if($empty)
                            <p class="mt-1 text-xs text-slate-400">Aucun poste sur ce mandat.</p>
                        @else
                            <ul class="mt-3 space-y-2">
                                @foreach($group['memberships'] as $membership)
                                    @include('memberships.partials.membership-item', ['membership' => $membership, 'carried' => false, 'held' => $heldPosts->get($membership->position_id)])
                                @endforeach
                                @foreach($group['carried'] as $membership)
                                    @include('memberships.partials.membership-item', ['membership' => $membership, 'carried' => true, 'held' => $heldPosts->get($membership->position_id)])
                                @endforeach

                                @foreach($group['participations'] as $participation)
                                    @php
                                        [$pLabel, $pClass] = \App\Models\MandateParticipation::STATUSES[$participation->status] ?? [$participation->status, 'bg-slate-100 text-slate-600'];
                                    @endphp
                                    <li class="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-amber-100 bg-amber-50/40 px-4 py-3">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left text-xs"></i></span>
                                            <div class="min-w-0">
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Participation déclarée</p>
                                                <p class="text-sm font-semibold text-slate-800">{{ $participation->position_title ?? 'Membre' }} <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $pClass }}">{{ $pLabel }}</span></p>
                                                <p class="text-xs text-slate-500">{{ $participation->node?->title ?? \App\Support\OrgScopes::label($participation->scope) }} · {{ \App\Support\OrgScopes::label($participation->scope) }}</p>
                                                @if($participation->status === 'rejected' && filled($participation->rejection_reason))
                                                    <p class="mt-1 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-800">Motif : {{ $participation->rejection_reason }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <form method="post" action="{{ route('memberships.past.destroy', $participation) }}" onsubmit="return confirm('Retirer cette participation ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-xl border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">{{ $participation->status === 'pending' ? 'Retirer ma demande' : 'Retirer' }}</button>
                                        </form>
                                    </li>
                                @endforeach

                                @foreach($group['positions']->reject(fn ($a) => in_array($a->node_id, $mergedNodes, true)) as $assignment)
                                    @php
                                        $ongoing = $assignment->ends_at === null || $assignment->ends_at->toDateString() >= now()->toDateString();
                                        $detail = collect([$assignment->node?->subtitle, $assignment->person?->job_title])->filter()->unique()->implode(' · ');
                                    @endphp
                                    <li class="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-violet-100 bg-violet-50/40 px-4 py-3">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700" aria-hidden="true"><i class="fa-solid fa-briefcase text-xs"></i></span>
                                            <div class="min-w-0">
                                                <p class="text-[11px] font-semibold uppercase tracking-wide text-violet-700">Poste</p>
                                                <p class="text-sm font-semibold text-slate-800">{{ $assignment->node?->title ?? 'Poste supprimé' }}
                                                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $ongoing ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $ongoing ? 'En poste' : 'Terminé' }}</span>
                                                </p>
                                                @if($detail !== '')<p class="text-xs text-slate-500">{{ $detail }}</p>@endif
                                                <p class="text-xs text-slate-400">{{ $assignment->starts_at?->format('d/m/Y') ?? '—' }} → {{ $assignment->ends_at?->format('d/m/Y') ?? 'aujourd’hui' }}</p>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                </li>
            @endforeach
        </ol>
    @endif
</section>
