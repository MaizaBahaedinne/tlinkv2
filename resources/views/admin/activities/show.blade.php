@extends('admin.layouts.app')

@section('title', $activity->title)
@section('section', 'Défis')

@section('content')
    <x-admin.page-header :title="$activity->title" :subtitle="($activity->challenge?->title ?? 'Activité libre').' · '.$activity->typeLabel().' · '.$activity->starts_at->format('d/m/Y H:i')">
        <x-slot:actions>
            <a href="{{ route('admin.activities.index') }}" class="btn btn-secondary"><x-admin.icon name="arrow-left" :size="16" /> Retour</a>
            <a href="{{ route('admin.activities.qr', $activity) }}" class="btn btn-secondary"><x-admin.icon name="activity" :size="16" /> QR code de présence</a>
            @if($canLead)<a href="{{ route('admin.activities.edit', $activity) }}" class="btn btn-primary"><x-admin.icon name="edit" :size="16" /> Modifier</a>@endif
            @if($canLead)
                <form method="post" action="{{ route('admin.activities.destroy', $activity) }}" data-confirm="{{ $activity->registrations()->exists() ? 'Les inscrits seront prévenus et leur inscription annulée. ' : '' }}« {{ $activity->title }} » sera supprimée définitivement." data-confirm-title="Supprimer cette activité ?" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger"><x-admin.icon name="trash" :size="16" /> Supprimer</button>
                </form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @include('admin.activities.partials.tasks-manager')

    <div class="grid-3-1">
        <article class="card card-flush" style="min-width:0">
            <div class="card-head"><div><h2>Inscriptions</h2><div class="sub">{{ $registrations->where('status', 'registered')->count() }} inscrit(s){{ $activity->capacity ? ' sur '.$activity->capacity.' places' : '' }} · {{ $registrations->whereNotNull('checked_in_at')->count() }} présent(s) validé(s)</div></div></div>
            @if($registrations->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Participant</th><th>Réponses</th><th>Statut</th><th>Présence</th></tr></thead>
                        <tbody>
                            @foreach($registrations as $registration)
                                <tr>
                                    <td><div class="cell-main">{{ $registration->user?->name ?? 'Utilisateur supprimé' }}</div><div class="cell-sub">{{ $registration->user?->email }}</div></td>
                                    <td class="text-sm">@foreach((array) $registration->answers as $answer)<div><span class="text-muted">{{ $answer['label'] ?? '' }} :</span> {{ $answer['value'] ?? '' }}</div>@endforeach</td>
                                    <td>{{ \App\Models\ActivityRegistration::STATUSES[$registration->status] ?? $registration->status }}</td>
                                    <td>
                                        <form method="post" action="{{ route('admin.activities.registrations.update', [$activity, $registration]) }}" class="inline-form">
                                            @csrf @method('PATCH')
                                            @if($registration->checked_in_at)
                                                <span class="badge badge-success" title="{{ $registration->checked_in_at->format('d/m/Y H:i') }}">Présent</span>
                                                <input type="hidden" name="presence" value="absent"><button class="btn btn-ghost btn-sm" type="submit">Retirer</button>
                                            @else
                                                <input type="hidden" name="presence" value="present"><button class="btn btn-secondary btn-sm" type="submit">Marquer présent</button>
                                            @endif
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-admin.empty icon="users" title="Aucune inscription" text="Personne ne s’est encore inscrit à cette activité." />
            @endif
        </article>

        <div style="min-width:0;display:grid;gap:22px;align-content:start">
            <article class="card">
                <div class="card-head"><div><h2>Informations</h2></div></div>
                <div class="card-body text-sm">
                    <p><strong>Club porteur :</strong> {{ $activity->club->title }}</p>
                    @if($activity->hasChallenge())
                        <p><strong>Nature :</strong> {{ $activity->in_theme ? 'Dans la thématique' : 'Hors thématique' }}</p>
                    @else
                        <p><strong>Défi :</strong> aucun (activité libre)</p>
                    @endif
                    <p><strong>Public :</strong> {{ $activity->audienceLabel() }}@if($activity->department) — {{ $activity->department->title }}@endif</p>
                    <p><strong>Statut :</strong> <x-admin.badge :status="$activity->status" /></p>
                    @if($activity->hasChallenge())
                        <p><strong>Note du comité :</strong> @if(($total['total'] ?? null) !== null){{ rtrim(rtrim(number_format($total['total'], 2, ',', ''), '0'), ',') }} / {{ $total['max'] }}@else<span class="text-muted">pas encore notée</span>@endif</p>
                    @endif
                </div>
            </article>

            <article class="card">
                <div class="card-head"><div><h2>Compte rendu de clôture</h2><div class="sub">{{ $activity->hasChallenge() ? 'RH, marketing, partenaires et budget : validé par le président avant l’évaluation du comité.' : 'Tâches, RH, marketing, partenaires et budget : validé par le président du club.' }}</div></div></div>
                <div class="card-body text-sm">
                    @php
                        $report = $activity->report;
                    @endphp
                    @if($report)
                        <p><strong>Statut :</strong> <span class="badge badge-{{ ['draft' => 'neutral', 'pending' => 'warning', 'validated' => 'success'][$report->status] ?? 'neutral' }}">{{ $report->statusLabel() }}</span></p>
                    @elseif($activity->isFinished())
                        <p class="text-muted">Pas encore rédigé : l’activité est terminée, le club doit remettre son compte rendu.</p>
                    @else
                        <p class="text-muted">Disponible une fois l’activité terminée.</p>
                    @endif
                    @if($report || $activity->isFinished())
                        <a href="{{ route('admin.activities.report', $activity) }}" class="btn btn-secondary btn-sm" style="margin-top:8px">{{ $report && ! $report->isEditable() ? 'Consulter' : 'Rédiger' }} le compte rendu</a>
                    @endif
                </div>
            </article>

            <article class="card">
                <div class="card-head"><div><h2>Co-organisateurs</h2><div class="sub">Chaque club organisateur reçoit la note complète de l’activité.</div></div></div>
                <div class="card-body text-sm">
                    @forelse($activity->organizers as $organizer)
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px">
                            <span><strong>{{ $organizer->club?->title }}</strong><br><span class="text-muted">{{ \App\Models\ActivityOrganizer::STATUSES[$organizer->status] ?? $organizer->status }}</span></span>
                            @if($canLead || ($manageable === null || in_array($organizer->club_id, $manageable, true)))
                                <x-admin.delete-button :action="route('admin.activities.organizers.destroy', $organizer)" :message="'« '.$organizer->club?->title.' » ne co-organisera plus cette activité.'" title="Retirer ce club ?" />
                            @endif
                        </div>
                    @empty
                        <p class="text-muted">Aucun co-organisateur.</p>
                    @endforelse

                    @if($canLead && $candidates->isNotEmpty())
                        <form method="post" action="{{ route('admin.activities.organizers.store', $activity) }}" style="margin-top:12px">
                            @csrf
                            <div class="field {{ $errors->has('club_id') ? 'has-error' : '' }}">
                                <label for="f-co-club">Inviter un club</label>
                                <select id="f-co-club" name="club_id">@foreach($candidates as $club)<option value="{{ $club->id }}">{{ $club->title }}</option>@endforeach</select>
                                @error('club_id')<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <button class="btn btn-secondary btn-sm" type="submit" style="margin-top:8px">Envoyer l’invitation</button>
                        </form>
                    @endif
                </div>
            </article>
        </div>
    </div>
@endsection
