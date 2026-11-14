@extends('admin.layouts.app')

@section('title', $meeting->title)
@section('section', 'Gouvernance')

@section('content')
    <x-admin.page-header :title="$meeting->title" :subtitle="$meeting->starts_at->translatedFormat('l d F Y \à H:i').' · '.$meeting->duration_minutes.' min · '.($meeting->club?->title ?? 'Fondation')">
        <x-slot:actions>
            <a href="{{ route('admin.meetings.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Réunions</a>
            <button type="button" class="btn btn-secondary" onclick="window.print()"><x-admin.icon name="file-text" :size="16" /> Imprimer</button>
            @if($canUpdate)<a href="{{ route('admin.meetings.edit', $meeting) }}" class="btn btn-secondary"><x-admin.icon name="edit" :size="16" /> Modifier</a>@endif
            @if($canDelete)<x-admin.delete-button :action="route('admin.meetings.destroy', $meeting)" message="La réunion, son ordre du jour et ses présences seront supprimés." title="Supprimer cette réunion ?" />@endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid-2" style="align-items:start">
        <div style="display:grid;gap:16px">
            <article class="card">
                <div class="card-head">
                    <div><h2>Informations</h2></div>
                    <span class="badge badge-{{ $meeting->status === 'held' ? 'success' : ($meeting->status === 'cancelled' ? 'neutral' : 'info') }}">{{ \App\Models\Meeting::STATUSES[$meeting->status] }}</span>
                </div>
                <div class="card-body" style="display:grid;gap:8px">
                    <p><strong>Lieu :</strong> {{ $meeting->location ?: '—' }}@if($meeting->online_url) · <a href="{{ $meeting->online_url }}" target="_blank" rel="noopener noreferrer">Lien en ligne</a>@endif</p>
                    <p><strong>Organisateur :</strong> {{ $meeting->organizer?->name ?? '—' }}</p>
                    <p><strong>Sujet :</strong> {{ $meeting->subject ?: '—' }}</p>
                    @if($meeting->guests)<p><strong>Invités externes :</strong> {{ $meeting->guests }}</p>@endif
                    @if($canUpdate)
                        <form method="post" action="{{ route('admin.meetings.status', $meeting) }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
                            @csrf
                            @if($meeting->status !== 'held')<button name="status" value="held" class="btn btn-secondary btn-sm" type="submit"><x-admin.icon name="check" :size="15" /> Marquer comme tenue</button>@endif
                            @if($meeting->status !== 'planned')<button name="status" value="planned" class="btn btn-secondary btn-sm" type="submit">Replanifier</button>@endif
                            @if($meeting->status !== 'cancelled')<button name="status" value="cancelled" class="btn btn-danger btn-sm" type="submit" data-confirm="Annuler la réunion ? Les invités déjà prévenus recevront une notification." data-confirm-title="Annuler la réunion ?"><x-admin.icon name="x" :size="15" /> Annuler</button>@endif
                        </form>
                    @endif
                </div>
            </article>

            <article class="card card-flush">
                <div class="card-head">
                    <div>
                        <h2>Ordre du jour</h2>
                        <p class="sub">{{ $meeting->items->count() }} point(s) · {{ $plannedMinutes }} min prévues sur {{ $meeting->duration_minutes }}.
                            @if($plannedMinutes > $meeting->duration_minutes)<strong style="color:var(--danger,#b42318)">L’ordre du jour dépasse la durée de la réunion.</strong>@endif</p>
                    </div>
                </div>

                @forelse($meeting->items as $item)
                    <details style="border-top:1px solid var(--line);padding:10px 16px" @if($meeting->status === 'held' && $item->outcome === 'pending') open @endif>
                        <summary style="cursor:pointer">
                            <strong>{{ $loop->iteration }}. {{ $item->title }}</strong>
                            @if($item->duration_minutes)<span class="text-sm text-muted">· {{ $item->duration_minutes }} min</span>@endif
                            @if($item->presenter)<span class="text-sm text-muted">· {{ $item->presenter->name }}</span>@endif
                            <span class="badge badge-{{ in_array($item->outcome, ['adopted'], true) ? 'success' : ($item->outcome === 'rejected' ? 'danger' : ($item->outcome === 'pending' ? 'neutral' : 'info')) }}">{{ \App\Models\MeetingAgendaItem::OUTCOMES[$item->outcome] }}</span>
                        </summary>
                        @if($item->description)<p class="text-sm" style="margin:8px 0">{{ $item->description }}</p>@endif
                        @if($item->decision)<p class="text-sm" style="margin:8px 0"><strong>Décision :</strong> {{ $item->decision }}</p>@endif
                        @if($canUpdate)
                            <form method="post" action="{{ route('admin.meetings.items.update', [$meeting, $item]) }}" class="form-grid" style="margin-top:10px">
                                @csrf @method('PUT')
                                @include('admin.meetings._item-fields', ['item' => $item, 'attendeeOptions' => $attendeeOptions])
                                <div class="span-2" style="display:flex;justify-content:flex-end"><button class="btn btn-primary btn-sm" type="submit">Enregistrer le point et la décision</button></div>
                            </form>
                            <div style="display:flex;gap:8px;margin-top:6px">
                                <form method="post" action="{{ route('admin.meetings.items.move', [$meeting, $item]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-secondary btn-sm" type="submit" @disabled($loop->first) aria-label="Monter">↑</button></form>
                                <form method="post" action="{{ route('admin.meetings.items.move', [$meeting, $item]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-secondary btn-sm" type="submit" @disabled($loop->last) aria-label="Descendre">↓</button></form>
                                <form method="post" action="{{ route('admin.meetings.items.destroy', [$meeting, $item]) }}" data-confirm="Retirer ce point de l’ordre du jour ?" data-confirm-title="Retirer ce point ?">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" type="submit"><x-admin.icon name="trash" :size="15" /> Retirer</button></form>
                            </div>
                        @endif
                    </details>
                @empty
                    <x-admin.empty icon="file-text" title="Ordre du jour vide" text="Ajoutez les points à traiter." />
                @endforelse

                @if($canUpdate)
                    <form method="post" action="{{ route('admin.meetings.items.store', $meeting) }}" class="form-grid" style="padding:14px 16px;border-top:1px solid var(--line)">
                        @csrf
                        <div class="field"><label for="i-title">Nouveau point <span class="req">*</span></label><input id="i-title" type="text" name="title" maxlength="190" required></div>
                        <div class="field"><label for="i-dur">Durée (min)</label><input id="i-dur" type="number" name="duration_minutes" min="1" max="600"></div>
                        <div class="span-2" style="display:flex;justify-content:flex-end"><button class="btn btn-secondary" type="submit"><x-admin.icon name="plus" :size="16" /> Ajouter à l’ordre du jour</button></div>
                    </form>
                @endif
            </article>

            <article class="card">
                <div class="card-head"><div><h2>Compte rendu</h2><p class="sub">Synthèse libre de la réunion, en plus des décisions par point.</p></div></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.meetings.minutes', $meeting) }}" style="display:grid;gap:10px">
                        @csrf @method('PUT')
                        <textarea name="minutes" rows="6" maxlength="20000" @disabled(! $canUpdate)>{{ old('minutes', $meeting->minutes) }}</textarea>
                        @if($canUpdate)<div style="display:flex;justify-content:flex-end"><button class="btn btn-primary" type="submit"><x-admin.icon name="check" :size="16" /> Enregistrer</button></div>@endif
                    </form>
                </div>
            </article>
        </div>

        <article class="card card-flush">
            <div class="card-head">
                <div><h2>Invités et présences</h2><p class="sub">{{ $meeting->attendees->count() }} invité(s) · {{ $meeting->attendees->where('response', 'accepted')->count() }} confirmé(s) · {{ $meeting->attendees->where('attendance', 'present')->count() }} présent(s).</p></div>
                @if($canUpdate && $meeting->isOpen())
                    <form method="post" action="{{ route('admin.meetings.invitations', $meeting) }}">
                        @csrf
                        <button class="btn btn-primary btn-sm" type="submit" @disabled($toInvite === 0)><x-admin.icon name="message" :size="15" /> Envoyer les invitations ({{ $toInvite }})</button>
                    </form>
                @endif
            </div>

            @if($meeting->attendees->count())
                <form method="post" action="{{ route('admin.meetings.attendance', $meeting) }}">
                    @csrf @method('PUT')
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>Invité</th><th>Réponse</th><th>Présence</th><th></th></tr></thead>
                            <tbody>
                                @foreach($meeting->attendees as $attendee)
                                    <tr>
                                        <td><span class="cell-main">{{ $attendee->user?->name }}</span><div class="cell-sub">{{ $attendee->invited_at ? 'Invité le '.$attendee->invited_at->format('d/m') : ($attendee->user_id === $meeting->organizer_id ? 'Organisateur' : 'Pas encore invité') }}</div></td>
                                        <td>{{ \App\Models\MeetingAttendee::RESPONSES[$attendee->response] }}</td>
                                        <td>
                                            <select name="attendance[{{ $attendee->user_id }}]" aria-label="Présence de {{ $attendee->user?->name }}" @disabled(! $canUpdate)>
                                                <option value="">—</option>
                                                @foreach(\App\Models\MeetingAttendee::ATTENDANCE as $key => $label)<option value="{{ $key }}" @selected($attendee->attendance === $key)>{{ $label }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="cell-actions">
                                            @if($canUpdate && $attendee->user_id !== $meeting->organizer_id)
                                                <button type="submit" form="rm-{{ $attendee->id }}" class="btn btn-danger btn-icon btn-sm" aria-label="Retirer {{ $attendee->user?->name }}" title="Retirer"><x-admin.icon name="x" :size="14" /></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($canUpdate)<div class="form-actions"><button class="btn btn-primary btn-sm" type="submit"><x-admin.icon name="check" :size="15" /> Enregistrer les présences</button></div>@endif
                </form>
                @foreach($meeting->attendees as $attendee)
                    <form id="rm-{{ $attendee->id }}" method="post" action="{{ route('admin.meetings.attendees.destroy', [$meeting, $attendee]) }}" hidden>@csrf @method('DELETE')</form>
                @endforeach
            @else
                <x-admin.empty icon="users" title="Aucun invité" />
            @endif

            @if($canUpdate)
                <div class="card-body" style="border-top:1px solid var(--line);display:grid;gap:12px">
                    <h3 style="margin:0">Inviter</h3>
                    @if($meeting->club_id)
                        <form method="post" action="{{ route('admin.meetings.attendees.store', $meeting) }}" style="display:flex;gap:8px;flex-wrap:wrap">
                            @csrf
                            <button class="btn btn-secondary btn-sm" name="group" value="team" type="submit"><x-admin.icon name="users" :size="15" /> Toute l’équipe du club</button>
                            <button class="btn btn-secondary btn-sm" name="group" value="members" type="submit"><x-admin.icon name="users" :size="15" /> Tous les membres du club</button>
                        </form>
                    @endif
                    <form method="get" style="display:flex;gap:8px">
                        <input type="search" name="u" value="{{ $term }}" placeholder="Rechercher un membre (nom ou e-mail)" maxlength="80" aria-label="Rechercher un membre" style="flex:1">
                        <button class="btn btn-secondary btn-sm" type="submit"><x-admin.icon name="search" :size="15" /> Chercher</button>
                    </form>
                    @if($term !== '')
                        @if($found->isEmpty())
                            <p class="text-sm text-muted">Aucun membre trouvé (ou déjà invité).</p>
                        @else
                            <form method="post" action="{{ route('admin.meetings.attendees.store', $meeting) }}" style="display:grid;gap:6px">
                                @csrf
                                @foreach($found as $candidate)
                                    <label class="check"><input type="checkbox" name="user_ids[]" value="{{ $candidate->id }}" checked> {{ $candidate->name }} <span class="text-sm text-muted">{{ $candidate->email }}</span></label>
                                @endforeach
                                <div><button class="btn btn-primary btn-sm" type="submit"><x-admin.icon name="plus" :size="15" /> Ajouter la sélection</button></div>
                            </form>
                        @endif
                    @endif
                </div>
            @endif
        </article>
    </div>
@endsection
