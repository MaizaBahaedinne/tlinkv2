@extends('admin.layouts.app')

@section('title', $ticket->reference.' · Mentorat')
@section('section', 'Tickets de mentorat')

@section('content')
    @php
        $steps = ['new' => 'Reçue', 'qualified' => 'Qualifiée', 'assigned' => 'Affectée', 'in_progress' => 'En accompagnement', 'resolved' => 'Résolue', 'closed' => 'Clôturée'];
        $stepKeys = array_keys($steps);
        $position = array_search($ticket->status, $stepKeys, true);
        $canComment = in_array($ticket->status, ['new', 'qualified', 'assigned', 'in_progress', 'resolved'], true);
        $rex = $ticket->rex;
        $tones = ['violet' => 'info'];
        $priorityTones = ['low' => 'neutral', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger'];
        $max = \App\Support\Mentoring\Mentoring::MAX_TEXT;
        $canWriteRex = $staff && in_array($ticket->status, ['in_progress', 'resolved'], true);
    @endphp

    <x-admin.page-header :title="$ticket->reference" :subtitle="$ticket->title">
        <x-slot:actions>
            <a href="{{ route('admin.mentoring.tickets.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour aux tickets</a>
        </x-slot:actions>
    </x-admin.page-header>

    @if($errors->any())
        <div class="alert alert-error" role="alert">
            <x-admin.icon name="x" />
            <span>@foreach($errors->all() as $message){{ $message }} @endforeach</span>
        </div>
    @endif

    <article class="card ticket-hero">
        <div class="ticket-tags">
            <span class="ticket-ref">{{ $ticket->reference }}</span>
            <span class="badge badge-{{ $tones[$ticket->statusTone()] ?? $ticket->statusTone() }}">{{ $ticket->statusLabel() }}</span>
            <span class="badge badge-neutral">{{ \App\Models\MentoringTicket::TYPES[$ticket->type] ?? $ticket->type }}</span>
            <span class="badge badge-neutral">{{ $ticket->collectiveLabel() ?? 'Personnel' }}</span>
            @if($ticket->category !== null)
                <span class="badge badge-{{ $priorityTones[$ticket->priority] ?? 'neutral' }}">Priorité {{ mb_strtolower(\App\Models\MentoringTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority) }}</span>
            @endif
        </div>

        <div>
            <h2>{{ $ticket->title }}</h2>
            <div class="ticket-who" style="margin-top:10px">
                <x-admin.avatar :name="$ticket->requester?->name" :size="28" />
                <span>Remontée par <strong>{{ $ticket->requester?->name ?? 'un ancien membre' }}</strong></span>
                <span>le {{ $ticket->created_at->format('d/m/Y à H:i') }}</span>
            </div>
        </div>

        @if($ticket->status === 'rejected')
            <div class="callout is-danger">
                <x-admin.icon name="x" :size="16" />
                <span>Cette demande n’a pas été retenue. Le motif figure dans le suivi ci-dessous.</span>
            </div>
        @else
            <ol class="stepper" aria-label="Avancement">
                @foreach($steps as $key => $label)
                    @php
                        $index = array_search($key, $stepKeys, true);
                        $stepClass = $key === $ticket->status ? 'is-current' : ($position !== false && $index < $position ? 'is-done' : '');
                    @endphp
                    <li class="{{ $stepClass }}" @if($key === $ticket->status) aria-current="step" @endif>{{ $label }}</li>
                @endforeach
            </ol>
        @endif
    </article>

    <div class="grid-3-1">
        <div class="stack" style="min-width:0">
            <article class="card card-flush">
                <div class="card-head"><h2>Description de la demande</h2></div>
                <div class="card-body prose">{{ $ticket->description }}</div>
            </article>

            <article class="card card-flush" id="fil">
                <div class="card-head">
                    <div>
                        <h2>Suivi</h2>
                        <div class="sub">{{ $events->count() }} événement(s)</div>
                    </div>
                </div>

                <div class="thread">
                    @foreach($events as $event)
                        @php
                            $internal = in_array($event->kind, ['internal', 'rex'], true);
                            $quiet = in_array($event->kind, ['status', 'qualification', 'assignment', 'rex'], true);
                            $fromRequester = (int) $event->user_id === (int) $ticket->requester_id;
                        @endphp
                        @if($quiet)
                            <div class="log">
                                <span>{{ $event->body }}</span>
                                @if($internal)<span class="badge badge-warning">Interne</span>@endif
                                <time>{{ $event->created_at->format('d/m/Y H:i') }}</time>
                            </div>
                        @else
                            <div class="msg {{ $internal ? 'is-internal' : ($fromRequester ? '' : 'is-team') }}">
                                <x-admin.avatar :name="$event->author?->name" :size="36" />
                                <div class="msg-body">
                                    <div class="msg-head">
                                        <strong>{{ $event->author?->name ?? 'Membre supprimé' }}</strong>
                                        @if($internal)<span class="badge badge-warning">Note interne</span>@elseif($fromRequester)<span class="badge badge-neutral">Demandeur</span>@endif
                                        <span>{{ $event->created_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <div class="msg-text">{{ $event->body }}</div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if($canComment && $staff)
                    <form method="post" action="{{ route('mentoring.comment', $ticket) }}" class="composer">
                        @csrf
                        <input type="hidden" name="ctx" value="admin">
                        <div class="field">
                            <label for="comment-body">Ajouter un message</label>
                            <textarea id="comment-body" name="body" rows="3" required maxlength="{{ $max }}" placeholder="Votre message au demandeur ou une note pour l’équipe…"></textarea>
                        </div>
                        <div class="composer-foot">
                            <label class="check"><input type="checkbox" name="internal" value="1"> Note interne (invisible du demandeur)</label>
                            <button type="submit" class="btn btn-primary">Envoyer</button>
                        </div>
                    </form>
                @endif
            </article>

            @if($rex !== null || in_array($ticket->status, ['in_progress', 'resolved'], true))
                <article class="card card-flush" id="rex">
                    <div class="card-head">
                        <div>
                            <h2>Retour d’expérience (REX)</h2>
                            @if($rex !== null)<div class="sub">Rédigé par {{ $rex->author?->name ?? 'un ancien mentor' }} · mis à jour le {{ $rex->updated_at->format('d/m/Y') }}</div>@endif
                        </div>
                        @if($rex !== null)<span class="badge badge-success">Rédigé</span>@else<span class="badge badge-warning">À rédiger</span>@endif
                    </div>

                    @if($canWriteRex)
                        <form method="post" action="{{ route('mentoring.rex', $ticket) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="ctx" value="admin">
                            <div class="card-body stack" style="gap:16px">
                                @foreach(['context' => 'Contexte', 'actions' => 'Actions menées', 'outcome' => 'Résultat', 'lessons' => 'Enseignements'] as $field => $label)
                                    <div class="field">
                                        <label for="rex-{{ $field }}">{{ $label }} <span class="req">*</span></label>
                                        <textarea id="rex-{{ $field }}" name="{{ $field }}" rows="3" required maxlength="{{ $max }}">{{ old($field, $rex?->{$field}) }}</textarea>
                                    </div>
                                @endforeach
                                <div class="field">
                                    <label for="rex-recommendations">Recommandations</label>
                                    <textarea id="rex-recommendations" name="recommendations" rows="3" maxlength="{{ $max }}">{{ old('recommendations', $rex?->recommendations) }}</textarea>
                                    <p class="hint">Facultatif : bonnes pratiques à partager, actions de prévention.</p>
                                </div>
                            </div>
                            <div class="form-actions"><button type="submit" class="btn btn-primary">Enregistrer le REX</button></div>
                        </form>
                    @elseif($rex !== null)
                        <dl class="rex-list">
                            @foreach(['context' => 'Contexte', 'actions' => 'Actions menées', 'outcome' => 'Résultat', 'lessons' => 'Enseignements', 'recommendations' => 'Recommandations'] as $field => $label)
                                @if(filled($rex->{$field}))
                                    <div>
                                        <dt>{{ $label }}</dt>
                                        <dd>{{ $rex->{$field} }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    @else
                        <x-admin.empty icon="file-text" title="REX à venir" text="Le REX sera rédigé par le mentor en charge de l’accompagnement." />
                    @endif
                </article>
            @endif
        </div>

        <aside class="stack" style="align-self:start;min-width:0">
            <article class="card card-flush">
                <div class="card-head"><h2>Informations</h2></div>
                <div class="card-body stack" style="gap:18px">
                    <div class="person">
                        <x-admin.avatar :name="$ticket->requester?->name" :size="40" />
                        <div>
                            <span class="person-label">Demandeur</span>
                            <span class="person-name">{{ $ticket->requester?->name ?? '—' }}</span>
                        </div>
                    </div>
                    <div class="person">
                        @if($ticket->mentor)
                            <x-admin.avatar :name="$ticket->mentor->name" :size="40" />
                        @else
                            <span class="avatar" style="width:40px;height:40px;background:var(--neutral-bg);color:var(--faint)" aria-hidden="true"><x-admin.icon name="user" :size="18" /></span>
                        @endif
                        <div>
                            <span class="person-label">Mentor</span>
                            <span class="person-name">{{ $ticket->mentor?->name ?? 'Pas encore affecté' }}</span>
                        </div>
                    </div>

                    <dl class="kv kv-1" style="padding-top:16px;border-top:1px solid var(--line)">
                        <div><dt>Catégorie</dt><dd>{{ $ticket->category !== null ? (\App\Models\MentoringTicket::CATEGORIES[$ticket->category] ?? $ticket->category) : 'À qualifier' }}</dd></div>
                        @if($ticket->category !== null)
                            <div><dt>Priorité</dt><dd>{{ \App\Models\MentoringTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</dd></div>
                        @endif
                        <div><dt>Reçue le</dt><dd>{{ $ticket->created_at->format('d/m/Y') }}</dd></div>
                        @if($ticket->resolved_at)<div><dt>Résolue le</dt><dd>{{ $ticket->resolved_at->format('d/m/Y') }}</dd></div>@endif
                    </dl>

                    @if(filled($ticket->qualification_note))
                        <div class="callout" style="flex-direction:column;gap:4px">
                            <strong style="font-size:11.5px;letter-spacing:.06em;text-transform:uppercase">Analyse du manager (interne)</strong>
                            <span class="prose" style="font-weight:500">{{ $ticket->qualification_note }}</span>
                        </div>
                    @endif
                </div>
            </article>

            @if($staff && $ticket->status === 'assigned')
                <article class="card card-flush">
                    <div class="card-head"><h2>Accompagnement</h2></div>
                    <form method="post" action="{{ route('mentoring.start', $ticket) }}" class="card-body">
                        @csrf
                        <input type="hidden" name="ctx" value="admin">
                        <button type="submit" class="btn btn-primary btn-block">Démarrer l’accompagnement</button>
                    </form>
                </article>
            @endif

            @if($staff && $ticket->status === 'in_progress')
                <article class="card card-flush">
                    <div class="card-head"><h2>Terminer</h2></div>
                    <form method="post" action="{{ route('mentoring.resolve', $ticket) }}" class="card-body stack">
                        @csrf
                        <input type="hidden" name="ctx" value="admin">
                        <p class="text-sm text-muted">{{ $rex === null ? 'Rédigez d’abord le REX pour pouvoir déclarer la demande résolue.' : 'Le REX est rédigé : vous pouvez déclarer la demande résolue.' }}</p>
                        <button type="submit" class="btn btn-primary btn-block" @disabled($rex === null)>Déclarer résolue</button>
                    </form>
                </article>
            @endif

            @if($manager)
                @if(in_array($ticket->status, ['new', 'qualified', 'assigned'], true))
                    <article class="card card-flush">
                        <div class="card-head"><h2>Qualifier</h2></div>
                        <form method="post" action="{{ route('mentoring.qualify', $ticket) }}" class="card-body stack">
                            @csrf
                            <input type="hidden" name="ctx" value="admin">
                            <div class="field">
                                <label for="q-category">Catégorie</label>
                                <select id="q-category" name="category" required>
                                    @if($ticket->category === null)<option value="">Choisir…</option>@endif
                                    @foreach(\App\Models\MentoringTicket::CATEGORIES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('category', $ticket->category) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="q-priority">Priorité</label>
                                <select id="q-priority" name="priority">
                                    @foreach(\App\Models\MentoringTicket::PRIORITIES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('priority', $ticket->priority) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="q-note">Analyse (interne)</label>
                                <textarea id="q-note" name="qualification_note" rows="3" maxlength="{{ $max }}">{{ old('qualification_note', $ticket->qualification_note) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">{{ $ticket->category === null ? 'Qualifier le ticket' : 'Mettre à jour la qualification' }}</button>
                        </form>
                    </article>
                @endif

                @if(in_array($ticket->status, ['qualified', 'assigned', 'in_progress'], true))
                    <article class="card card-flush">
                        <div class="card-head"><h2>{{ $ticket->mentor_id === null ? 'Affecter à un mentor' : 'Réaffecter' }}</h2></div>
                        @if($mentors->isEmpty())
                            <div class="card-body">
                                <p class="text-sm text-muted">Aucun mentor disponible. <a href="{{ route('admin.mentoring.mentors.index') }}" style="color:var(--navy-800);font-weight:700">Ajoutez-en dans l’annuaire.</a></p>
                            </div>
                        @else
                            <form method="post" action="{{ route('mentoring.assign', $ticket) }}" class="card-body stack">
                                @csrf
                                <input type="hidden" name="ctx" value="admin">
                                <div class="field">
                                    <label for="a-mentor">Mentor</label>
                                    <select id="a-mentor" name="mentor_id" required>
                                        <option value="">Choisir un mentor…</option>
                                        @foreach($mentors as $row)
                                            <option value="{{ $row->user_id }}" @selected((int) $ticket->mentor_id === (int) $row->user_id)>{{ $row->user?->name }} — {{ $workload[$row->user_id] ?? 0 }} en cours{{ filled($row->specialties) ? ' · '.$row->specialties : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">Affecter</button>
                            </form>
                        @endif
                    </article>
                @endif

                @if(in_array($ticket->status, ['new', 'qualified'], true))
                    <article class="card card-flush">
                        <div class="card-head"><h2>Écarter</h2></div>
                        <form method="post" action="{{ route('mentoring.reject', $ticket) }}" class="card-body stack">
                            @csrf
                            <input type="hidden" name="ctx" value="admin">
                            <div class="field">
                                <label for="r-reason">Motif communiqué au demandeur</label>
                                <textarea id="r-reason" name="reason" rows="2" required maxlength="1000"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger btn-block">Ne pas retenir la demande</button>
                        </form>
                    </article>
                @endif

                @if($ticket->status === 'resolved')
                    <article class="card card-flush">
                        <div class="card-head"><h2>Clôture</h2></div>
                        <div class="card-body stack" style="gap:10px">
                            <form method="post" action="{{ route('mentoring.close', $ticket) }}">@csrf<input type="hidden" name="ctx" value="admin"><button type="submit" class="btn btn-primary btn-block">Clôturer le ticket</button></form>
                            <form method="post" action="{{ route('mentoring.reopen', $ticket) }}">@csrf<input type="hidden" name="ctx" value="admin"><button type="submit" class="btn btn-secondary btn-block">Rouvrir l’accompagnement</button></form>
                        </div>
                    </article>
                @elseif($ticket->status === 'closed' && $ticket->mentor_id !== null)
                    <article class="card card-flush">
                        <div class="card-head"><h2>Réouverture</h2></div>
                        <form method="post" action="{{ route('mentoring.reopen', $ticket) }}" class="card-body">@csrf<input type="hidden" name="ctx" value="admin"><button type="submit" class="btn btn-secondary btn-block">Rouvrir l’accompagnement</button></form>
                    </article>
                @endif
            @endif
        </aside>
    </div>
@endsection
