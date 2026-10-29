@extends('layouts.base', ['title' => $ticket->reference.' | Mentorat | T.Link'])

@section('content')
@php
    $steps = ['new' => 'Reçue', 'qualified' => 'Qualifiée', 'assigned' => 'Affectée', 'in_progress' => 'En accompagnement', 'resolved' => 'Résolue', 'closed' => 'Clôturée'];
    $order = array_keys($steps);
    $position = array_search($ticket->status, $order, true);
    $input = 'mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-tlink-blue';
    $primary = 'rounded-xl bg-tlink-blue px-4 py-2 text-sm font-semibold text-white hover:opacity-90';
    $secondary = 'rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50';
    $canComment = in_array($ticket->status, ['new', 'qualified', 'assigned', 'in_progress', 'resolved'], true);
    $rex = $ticket->rex;
@endphp
<div class="min-h-screen bg-tlink-sand pb-12 text-slate-800">
    @include('partials.app-header')

    <div class="app-container space-y-5 py-6">
        <div>
            <a href="{{ route('mentoring.index') }}" class="text-sm font-semibold text-tlink-blue hover:underline"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i> Mentorat</a>
        </div>

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Colonne principale : ticket, parcours, fil de discussion, REX --}}
            <div class="space-y-5 lg:col-span-2">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-slate-500">{{ $ticket->reference }}</span>
                        @include('mentoring.partials.badge', ['label' => $ticket->statusLabel(), 'tone' => $ticket->statusTone()])
                        @include('mentoring.partials.badge', ['label' => \App\Models\MentoringTicket::TYPES[$ticket->type] ?? $ticket->type, 'tone' => 'neutral'])
                        @include('mentoring.partials.badge', ['label' => $ticket->collectiveLabel() ?? 'Personnel', 'tone' => 'neutral'])
                    </div>
                    <h1 class="mt-2 font-display text-2xl font-semibold text-slate-900">{{ $ticket->title }}</h1>
                    <p class="mt-1 text-xs text-slate-500">Remontée par {{ $ticket->requester?->name ?? 'un ancien membre' }} le {{ $ticket->created_at->format('d/m/Y à H:i') }}</p>
                    <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $ticket->description }}</p>
                </section>

                @if($ticket->status === 'rejected')
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Cette demande n’a pas été retenue. Le motif figure dans le fil ci-dessous.</div>
                @else
                    <ol class="grid grid-cols-3 gap-2 rounded-3xl border border-slate-200 bg-white p-4 text-center text-[11px] font-semibold shadow-soft sm:grid-cols-6" aria-label="Avancement">
                        @foreach($steps as $key => $label)
                            @php
                                $index = array_search($key, $order, true);
                                $done = $position !== false && $index <= $position;
                            @endphp
                            <li class="rounded-xl px-1 py-2 {{ $done ? 'bg-rose-50 text-tlink-blue' : 'bg-slate-50 text-slate-400' }}" @if($key === $ticket->status) aria-current="step" @endif>{{ $label }}</li>
                        @endforeach
                    </ol>
                @endif

                <section id="fil" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Suivi</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach($events as $event)
                            @php
                                $internal = in_array($event->kind, ['internal', 'rex'], true);
                                $quiet = in_array($event->kind, ['status', 'qualification', 'assignment', 'rex'], true);
                            @endphp
                            @if($quiet)
                                <li class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                                    <i class="fa-solid {{ $event->kind === 'rex' ? 'fa-file-lines' : 'fa-circle-info' }} text-slate-400" aria-hidden="true"></i>
                                    <span>{{ $event->body }}</span>
                                    @if($internal)<span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Interne</span>@endif
                                    <span class="text-slate-400">{{ $event->created_at->format('d/m/Y H:i') }}</span>
                                </li>
                            @else
                                <li class="rounded-2xl px-4 py-3 {{ $internal ? 'border border-dashed border-amber-300 bg-amber-50/60' : ((int) $event->user_id === (int) $ticket->requester_id ? 'bg-slate-50' : 'bg-rose-50/60') }}">
                                    <p class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        <span class="font-semibold text-slate-800">{{ $event->author?->name ?? 'Membre supprimé' }}</span>
                                        @if($internal)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Note interne</span>@endif
                                        <span>{{ $event->created_at->format('d/m/Y H:i') }}</span>
                                    </p>
                                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $event->body }}</p>
                                </li>
                            @endif
                        @endforeach
                    </ul>

                    @if($canComment)
                        <form method="post" action="{{ route('mentoring.comment', $ticket) }}" class="mt-5 space-y-3 border-t border-slate-100 pt-5">
                            @csrf
                            <label for="comment-body" class="text-sm font-semibold text-slate-700">Ajouter un message</label>
                            <textarea id="comment-body" name="body" rows="3" required maxlength="{{ \App\Support\Mentoring\Mentoring::MAX_TEXT }}" class="{{ $input }}"></textarea>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                @if($staff)
                                    <label class="inline-flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="internal" value="1" class="accent-tlink-blue"> Note interne (invisible du demandeur)</label>
                                @else
                                    <span></span>
                                @endif
                                <button type="submit" class="{{ $primary }}">Envoyer</button>
                            </div>
                        </form>
                    @endif
                </section>

                {{-- REX : réservé à l'équipe de mentors --}}
                @if($staff && ($rex !== null || in_array($ticket->status, ['in_progress', 'resolved'], true)))
                    <section id="rex" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-display text-lg font-semibold text-slate-900">Retour d’expérience (REX)</h2>
                            @if($rex !== null)<span class="text-xs text-slate-500">Rédigé par {{ $rex->author?->name ?? 'un ancien mentor' }} · mis à jour le {{ $rex->updated_at->format('d/m/Y') }}</span>@endif
                        </div>

                        @if($staff && in_array($ticket->status, ['in_progress', 'resolved'], true))
                            <form method="post" action="{{ route('mentoring.rex', $ticket) }}" class="mt-4 space-y-4">
                                @csrf
                                @method('PUT')
                                @foreach(['context' => ['Contexte', 'Situation de départ, acteurs, enjeux.'], 'actions' => ['Actions menées', 'Ce qui a été fait pour accompagner.'], 'outcome' => ['Résultat', 'Ce qui a changé, ce qui reste ouvert.'], 'lessons' => ['Enseignements', 'Ce qui a fonctionné, ce qui a bloqué.']] as $field => [$label, $hint])
                                    <div>
                                        <label for="rex-{{ $field }}" class="text-sm font-semibold text-slate-700">{{ $label }}</label>
                                        <textarea id="rex-{{ $field }}" name="{{ $field }}" rows="3" required maxlength="{{ \App\Support\Mentoring\Mentoring::MAX_TEXT }}" placeholder="{{ $hint }}" class="{{ $input }}">{{ old($field, $rex?->{$field}) }}</textarea>
                                    </div>
                                @endforeach
                                <div>
                                    <label for="rex-recommendations" class="text-sm font-semibold text-slate-700">Recommandations (facultatif)</label>
                                    <textarea id="rex-recommendations" name="recommendations" rows="3" maxlength="{{ \App\Support\Mentoring\Mentoring::MAX_TEXT }}" placeholder="Bonnes pratiques à partager, actions de prévention." class="{{ $input }}">{{ old('recommendations', $rex?->recommendations) }}</textarea>
                                </div>
                                <div class="flex justify-end"><button type="submit" class="{{ $primary }}">Enregistrer le REX</button></div>
                            </form>
                        @elseif($rex !== null)
                            <dl class="mt-4 space-y-4 text-sm">
                                @foreach(['context' => 'Contexte', 'actions' => 'Actions menées', 'outcome' => 'Résultat', 'lessons' => 'Enseignements', 'recommendations' => 'Recommandations'] as $field => $label)
                                    @if(filled($rex->{$field}))
                                        <div>
                                            <dt class="font-semibold text-slate-700">{{ $label }}</dt>
                                            <dd class="mt-1 whitespace-pre-line text-slate-600">{{ $rex->{$field} }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                        @endif
                    </section>
                @endif
            </div>

            {{-- Colonne latérale : informations et actions --}}
            <aside class="space-y-5">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Informations</h2>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Demandeur</dt><dd class="text-slate-700">{{ $ticket->requester?->name ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Mentor</dt><dd class="text-slate-700">{{ $ticket->mentor?->name ?? 'Pas encore affecté' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Catégorie</dt><dd class="text-slate-700">{{ $ticket->category !== null ? (\App\Models\MentoringTicket::CATEGORIES[$ticket->category] ?? $ticket->category) : 'À qualifier' }}</dd></div>
                        @if($ticket->category !== null)
                            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Priorité</dt><dd class="text-slate-700">{{ \App\Models\MentoringTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</dd></div>
                        @endif
                        @if($ticket->resolved_at)<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Résolue le</dt><dd class="text-slate-700">{{ $ticket->resolved_at->format('d/m/Y') }}</dd></div>@endif
                        @if($staff && filled($ticket->qualification_note))
                            <div><dt class="text-xs font-semibold uppercase tracking-wide text-amber-600">Analyse du manager (interne)</dt><dd class="whitespace-pre-line text-slate-700">{{ $ticket->qualification_note }}</dd></div>
                        @endif
                    </dl>
                </section>

                @if($staff && $ticket->status === 'assigned')
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Accompagnement</h2>
                        <form method="post" action="{{ route('mentoring.start', $ticket) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="{{ $primary }} w-full">Démarrer l’accompagnement</button>
                        </form>
                    </section>
                @endif

                @if($staff && $ticket->status === 'in_progress')
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Terminer</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ $rex === null ? 'Rédigez d’abord le REX pour pouvoir déclarer la demande résolue.' : 'Le REX est rédigé : vous pouvez déclarer la demande résolue.' }}</p>
                        <form method="post" action="{{ route('mentoring.resolve', $ticket) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="{{ $primary }} w-full disabled:opacity-50" @disabled($rex === null)>Déclarer résolue</button>
                        </form>
                    </section>
                @endif

                @if($adminUrl !== null)
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-soft">
                        <h2 class="font-display text-lg font-semibold text-slate-900">Gestion du ticket</h2>
                        <p class="mt-1 text-xs text-slate-500">Qualification, affectation, clôture et réouverture se font dans l’administration.</p>
                        <a href="{{ $adminUrl }}" class="{{ $secondary }} mt-3 block w-full text-center">Ouvrir dans l’administration</a>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</div>
@endsection
