@extends('admin.layouts.app')

@section('title', $ticket->reference.' · Mentorat')
@section('section', 'Tickets de mentorat')

@section('content')
    @php
        $steps = ['new' => 'Reçue', 'qualified' => 'Qualifiée', 'assigned' => 'Affectée', 'in_progress' => 'En accompagnement', 'resolved' => 'Résolue', 'closed' => 'Clôturée'];
        $position = array_search($ticket->status, array_keys($steps), true);
        $canComment = in_array($ticket->status, ['new', 'qualified', 'assigned', 'in_progress', 'resolved'], true);
        $rex = $ticket->rex;
        $tones = ['violet' => 'info'];
        $max = \App\Support\Mentoring\Mentoring::MAX_TEXT;
    @endphp

    <x-admin.page-header :title="$ticket->title" :subtitle="$ticket->reference.' · remontée par '.($ticket->requester?->name ?? 'un ancien membre').' le '.$ticket->created_at->format('d/m/Y à H:i')">
        <x-slot:actions>
            <a href="{{ route('admin.mentoring.tickets.index') }}" class="btn btn-secondary">Retour aux tickets</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <x-admin.icon name="x" />
            <span>@foreach($errors->all() as $message){{ $message }} @endforeach</span>
        </div>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
        <div style="flex:2 1 420px;min-width:0;display:grid;gap:16px">
            <article class="card">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
                    <span class="badge badge-{{ $tones[$ticket->statusTone()] ?? $ticket->statusTone() }}">{{ $ticket->statusLabel() }}</span>
                    <span class="badge badge-neutral">{{ \App\Models\MentoringTicket::TYPES[$ticket->type] ?? $ticket->type }}</span>
                    <span class="badge badge-neutral">{{ $ticket->collectiveLabel() ?? 'Personnel' }}</span>
                </div>
                <div style="white-space:pre-line">{{ $ticket->description }}</div>

                @if($ticket->status === 'rejected')
                    <p class="text-sm" style="margin:14px 0 0;color:var(--danger,#b91c1c)">Cette demande n’a pas été retenue. Le motif figure dans le suivi ci-dessous.</p>
                @else
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:16px" aria-label="Avancement">
                        @foreach($steps as $key => $label)
                            @php
                                $done = $position !== false && array_search($key, array_keys($steps), true) <= $position;
                            @endphp
                            <span class="badge badge-{{ $key === $ticket->status ? 'info' : ($done ? 'success' : 'neutral') }}" @if($key === $ticket->status) aria-current="step" @endif>{{ $label }}</span>
                        @endforeach
                    </div>
                @endif
            </article>

            <article class="card" id="fil">
                <h2 style="margin-top:0">Suivi</h2>
                <div style="display:grid;gap:10px">
                    @foreach($events as $event)
                        @php
                            $internal = in_array($event->kind, ['internal', 'rex'], true);
                            $quiet = in_array($event->kind, ['status', 'qualification', 'assignment', 'rex'], true);
                        @endphp
                        @if($quiet)
                            <p class="text-sm text-muted" style="margin:0">
                                {{ $event->body }}
                                @if($internal)<span class="badge badge-warning">Interne</span>@endif
                                · {{ $event->created_at->format('d/m/Y H:i') }}
                            </p>
                        @else
                            <div style="padding:10px 14px;border-radius:12px;{{ $internal ? 'border:1px dashed #f59e0b;background:#fffbeb' : 'background:var(--surface-2,#f8fafc)' }}">
                                <p class="text-sm text-muted" style="margin:0 0 4px">
                                    <strong style="display:inline">{{ $event->author?->name ?? 'Membre supprimé' }}</strong>
                                    @if($internal)<span class="badge badge-warning">Note interne</span>@endif
                                    · {{ $event->created_at->format('d/m/Y H:i') }}
                                </p>
                                <div style="white-space:pre-line">{{ $event->body }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if($canComment && $staff)
                    <form method="post" action="{{ route('mentoring.comment', $ticket) }}" style="margin-top:16px;display:grid;gap:10px">
                        @csrf
                        <input type="hidden" name="ctx" value="admin">
                        <label class="text-sm" for="comment-body">Ajouter un message</label>
                        <textarea id="comment-body" name="body" rows="3" required maxlength="{{ $max }}"></textarea>
                        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
                            <label class="check"><input type="checkbox" name="internal" value="1"> Note interne (invisible du demandeur)</label>
                            <button type="submit" class="btn btn-primary">Envoyer</button>
                        </div>
                    </form>
                @endif
            </article>

            @if($rex !== null || in_array($ticket->status, ['in_progress', 'resolved'], true))
                <article class="card" id="rex">
                    <h2 style="margin-top:0">Retour d’expérience (REX)</h2>
                    @if($rex !== null)<p class="text-sm text-muted">Rédigé par {{ $rex->author?->name ?? 'un ancien mentor' }} · mis à jour le {{ $rex->updated_at->format('d/m/Y') }}</p>@endif

                    @if($staff && in_array($ticket->status, ['in_progress', 'resolved'], true))
                        <form method="post" action="{{ route('mentoring.rex', $ticket) }}" style="display:grid;gap:12px">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="ctx" value="admin">
                            @foreach(['context' => 'Contexte', 'actions' => 'Actions menées', 'outcome' => 'Résultat', 'lessons' => 'Enseignements'] as $field => $label)
                                <div>
                                    <label class="text-sm" for="rex-{{ $field }}">{{ $label }}</label>
                                    <textarea id="rex-{{ $field }}" name="{{ $field }}" rows="3" required maxlength="{{ $max }}">{{ old($field, $rex?->{$field}) }}</textarea>
                                </div>
                            @endforeach
                            <div>
                                <label class="text-sm" for="rex-recommendations">Recommandations (facultatif)</label>
                                <textarea id="rex-recommendations" name="recommendations" rows="3" maxlength="{{ $max }}">{{ old('recommendations', $rex?->recommendations) }}</textarea>
                            </div>
                            <div style="display:flex;justify-content:flex-end"><button type="submit" class="btn btn-primary">Enregistrer le REX</button></div>
                        </form>
                    @elseif($rex !== null)
                        <dl style="display:grid;gap:12px;margin:0">
                            @foreach(['context' => 'Contexte', 'actions' => 'Actions menées', 'outcome' => 'Résultat', 'lessons' => 'Enseignements', 'recommendations' => 'Recommandations'] as $field => $label)
                                @if(filled($rex->{$field}))
                                    <div>
                                        <dt><strong>{{ $label }}</strong></dt>
                                        <dd style="margin:2px 0 0;white-space:pre-line">{{ $rex->{$field} }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    @else
                        <p class="text-sm text-muted" style="margin:0">Le REX sera rédigé par le mentor en charge de l’accompagnement.</p>
                    @endif
                </article>
            @endif
        </div>

        <aside style="flex:1 1 280px;min-width:0;display:grid;gap:16px">
            <article class="card">
                <h2 style="margin-top:0">Informations</h2>
                <dl style="display:grid;gap:10px;margin:0" class="text-sm">
                    <div><dt class="text-muted">Demandeur</dt><dd style="margin:0">{{ $ticket->requester?->name ?? '—' }}</dd></div>
                    <div><dt class="text-muted">Mentor</dt><dd style="margin:0">{{ $ticket->mentor?->name ?? 'Pas encore affecté' }}</dd></div>
                    <div><dt class="text-muted">Catégorie</dt><dd style="margin:0">{{ $ticket->category !== null ? (\App\Models\MentoringTicket::CATEGORIES[$ticket->category] ?? $ticket->category) : 'À qualifier' }}</dd></div>
                    @if($ticket->category !== null)
                        <div><dt class="text-muted">Priorité</dt><dd style="margin:0">{{ \App\Models\MentoringTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</dd></div>
                    @endif
                    @if($ticket->resolved_at)<div><dt class="text-muted">Résolue le</dt><dd style="margin:0">{{ $ticket->resolved_at->format('d/m/Y') }}</dd></div>@endif
                    @if(filled($ticket->qualification_note))
                        <div><dt class="text-muted">Analyse du manager (interne)</dt><dd style="margin:0;white-space:pre-line">{{ $ticket->qualification_note }}</dd></div>
                    @endif
                </dl>
            </article>

            @if($staff && $ticket->status === 'assigned')
                <article class="card">
                    <h2 style="margin-top:0">Accompagnement</h2>
                    <form method="post" action="{{ route('mentoring.start', $ticket) }}">
                        @csrf
                        <input type="hidden" name="ctx" value="admin">
                        <button type="submit" class="btn btn-primary" style="width:100%">Démarrer l’accompagnement</button>
                    </form>
                </article>
            @endif

            @if($staff && $ticket->status === 'in_progress')
                <article class="card">
                    <h2 style="margin-top:0">Terminer</h2>
                    <p class="text-sm text-muted">{{ $rex === null ? 'Rédigez d’abord le REX pour pouvoir déclarer la demande résolue.' : 'Le REX est rédigé : vous pouvez déclarer la demande résolue.' }}</p>
                    <form method="post" action="{{ route('mentoring.resolve', $ticket) }}">
                        @csrf
                        <input type="hidden" name="ctx" value="admin">
                        <button type="submit" class="btn btn-primary" style="width:100%" @disabled($rex === null)>Déclarer résolue</button>
                    </form>
                </article>
            @endif

            @if($manager)
                @if(in_array($ticket->status, ['new', 'qualified', 'assigned'], true))
                    <article class="card">
                        <h2 style="margin-top:0">Qualifier</h2>
                        <form method="post" action="{{ route('mentoring.qualify', $ticket) }}" style="display:grid;gap:10px">
                            @csrf
                            <input type="hidden" name="ctx" value="admin">
                            <div>
                                <label class="text-sm" for="q-category">Catégorie</label>
                                <select id="q-category" name="category" required>
                                    @if($ticket->category === null)<option value="">Choisir…</option>@endif
                                    @foreach(\App\Models\MentoringTicket::CATEGORIES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('category', $ticket->category) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm" for="q-priority">Priorité</label>
                                <select id="q-priority" name="priority">
                                    @foreach(\App\Models\MentoringTicket::PRIORITIES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('priority', $ticket->priority) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-sm" for="q-note">Analyse (interne)</label>
                                <textarea id="q-note" name="qualification_note" rows="3" maxlength="{{ $max }}">{{ old('qualification_note', $ticket->qualification_note) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">{{ $ticket->category === null ? 'Qualifier le ticket' : 'Mettre à jour la qualification' }}</button>
                        </form>
                    </article>
                @endif

                @if(in_array($ticket->status, ['qualified', 'assigned', 'in_progress'], true))
                    <article class="card">
                        <h2 style="margin-top:0">{{ $ticket->mentor_id === null ? 'Affecter à un mentor' : 'Réaffecter' }}</h2>
                        @if($mentors->isEmpty())
                            <p class="text-sm text-muted" style="margin:0">Aucun mentor disponible. <a href="{{ route('admin.mentoring.mentors.index') }}">Ajoutez-en dans l’annuaire.</a></p>
                        @else
                            <form method="post" action="{{ route('mentoring.assign', $ticket) }}" style="display:grid;gap:10px">
                                @csrf
                                <input type="hidden" name="ctx" value="admin">
                                <label class="text-sm" for="a-mentor">Mentor</label>
                                <select id="a-mentor" name="mentor_id" required>
                                    <option value="">Choisir un mentor…</option>
                                    @foreach($mentors as $row)
                                        <option value="{{ $row->user_id }}" @selected((int) $ticket->mentor_id === (int) $row->user_id)>{{ $row->user?->name }} — {{ $workload[$row->user_id] ?? 0 }} en cours{{ filled($row->specialties) ? ' · '.$row->specialties : '' }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary">Affecter</button>
                            </form>
                        @endif
                    </article>
                @endif

                @if(in_array($ticket->status, ['new', 'qualified'], true))
                    <article class="card">
                        <h2 style="margin-top:0">Écarter</h2>
                        <form method="post" action="{{ route('mentoring.reject', $ticket) }}" style="display:grid;gap:10px">
                            @csrf
                            <input type="hidden" name="ctx" value="admin">
                            <label class="text-sm" for="r-reason">Motif communiqué au demandeur</label>
                            <textarea id="r-reason" name="reason" rows="2" required maxlength="1000"></textarea>
                            <button type="submit" class="btn btn-danger">Ne pas retenir la demande</button>
                        </form>
                    </article>
                @endif

                @if($ticket->status === 'resolved')
                    <article class="card">
                        <h2 style="margin-top:0">Clôture</h2>
                        <div style="display:grid;gap:8px">
                            <form method="post" action="{{ route('mentoring.close', $ticket) }}">@csrf<input type="hidden" name="ctx" value="admin"><button type="submit" class="btn btn-primary" style="width:100%">Clôturer le ticket</button></form>
                            <form method="post" action="{{ route('mentoring.reopen', $ticket) }}">@csrf<input type="hidden" name="ctx" value="admin"><button type="submit" class="btn btn-secondary" style="width:100%">Rouvrir l’accompagnement</button></form>
                        </div>
                    </article>
                @elseif($ticket->status === 'closed' && $ticket->mentor_id !== null)
                    <article class="card">
                        <form method="post" action="{{ route('mentoring.reopen', $ticket) }}">@csrf<input type="hidden" name="ctx" value="admin"><button type="submit" class="btn btn-secondary" style="width:100%">Rouvrir l’accompagnement</button></form>
                    </article>
                @endif
            @endif
        </aside>
    </div>
@endsection
