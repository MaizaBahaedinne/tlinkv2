@extends('admin.layouts.app')

@section('title', $bus->name.' — Transport — '.$event->title)
@section('section', 'Événements')

@php
    $passengersByStop = $passengers->groupBy(fn ($passenger) => $passenger->stop_id ?: 0);
@endphp

@section('content')
    @include('admin.events.manage._header', ['subtitle' => 'Transport — '.$bus->name])

    <p style="margin-bottom:12px"><a href="{{ route('admin.events.transport.index', $event) }}" class="btn btn-ghost btn-sm">← Retour au transport</a></p>

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>{{ $bus->name }}@if($bus->plate) <span class="text-muted text-sm">({{ $bus->plate }})</span>@endif</h2>
                <div class="sub">
                    {{ $passengers->count() }} / {{ $bus->capacity }} passagers · {{ $passengers->where('boarding', 'present')->count() }} présent(s) · {{ $passengers->where('boarding', 'absent')->count() }} absent(s)
                </div>
            </div>
        </div>
        <div class="card-body">
            <p class="text-sm"><strong>Circuit :</strong>
                @foreach($bus->stops as $stop)
                    {{ $stop->point->label() }} ({{ $stop->departs_at?->format('H:i') ?? '—' }}){{ ! $loop->last ? ' → ' : '' }}
                @endforeach
                → {{ $event->location ?: 'événement' }}
            </p>
            <p class="text-sm text-muted">Responsables : {{ $bus->managers->map(fn ($manager) => $manager->user?->name.' ('.$manager->dutyLabel().')')->implode(', ') ?: '—' }}</p>
        </div>
    </article>

    @if($canAttendance && $waiting->count())
        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head">
                <div>
                    <h2>Passagers en attente d’un bus</h2>
                    <div class="sub">Ils ont choisi un point de départ de ce circuit ; affectez-les à ce bus ({{ $bus->remaining() }} place(s) restante(s)).</div>
                </div>
            </div>
            @error('booking') <p class="text-sm" style="padding:0 16px;color:var(--danger,#b91c1c)">{{ $message }}</p> @enderror
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Passager</th><th>Point de départ</th><th></th></tr></thead>
                    <tbody>
                        @foreach($waiting as $candidate)
                            <tr>
                                <td><div class="cell-main">{{ $candidate->user?->name }}</div><div class="cell-sub">{{ $candidate->user?->email }}</div></td>
                                <td>{{ $candidate->point?->label() }}</td>
                                <td class="text-right">
                                    <form method="post" action="{{ route('admin.events.transport.claim', [$event, $bus, $candidate]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">Affecter à ce bus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </article>
    @endif

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head">
            <div>
                <h2>Passagers par point de départ</h2>
                <div class="sub">Le responsable peut affecter un passager à un autre arrêt de ce bus, puis pointer sa présence.</div>
            </div>
        </div>
        @if($passengers->count())
            <div class="card-body" style="display:grid;gap:14px">
                @foreach($passengersByStop as $stopId => $group)
                    @php($stop = $group->first()->stop)
                    <article class="card" style="border:1px solid var(--line)">
                        <div class="card-head" style="padding:10px 14px">
                            <div>
                                <strong>Point de départ : {{ $stop?->point->label() ?? 'Non défini' }}</strong>
                                <div class="sub">{{ $group->count() }} passager(s) · départ {{ $stop?->departs_at?->format('d/m H:i') ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="table-wrap" style="border-top:1px solid var(--line)">
                            <table class="tbl">
                                <thead><tr><th>Passager</th><th>Affectation</th><th>Statut</th>@if($canAttendance)<th></th>@endif</tr></thead>
                                <tbody>
                                    @foreach($group as $passenger)
                                        <tr>
                                            <td><div class="cell-main">{{ $passenger->user?->name }}</div><div class="cell-sub">{{ $passenger->user?->email }}</div></td>
                                            <td>
                                                @if($canAttendance)
                                                    <form method="post" action="{{ route('admin.events.transport.assign', [$event, $bus, $passenger]) }}" class="inline-form" style="gap:6px;align-items:center">
                                                        @csrf @method('PATCH')
                                                        <select name="stop_id" aria-label="Point de départ du passager">
                                                            @foreach($bus->stops as $option)
                                                                <option value="{{ $option->id }}" @selected($passenger->stop_id === $option->id)>{{ $option->point->label() }}{{ $option->departs_at ? ' · '.$option->departs_at->format('d/m H:i') : '' }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" class="btn btn-secondary btn-sm">Affecter</button>
                                                    </form>
                                                @else
                                                    {{ $passenger->stop?->point->label() }}
                                                @endif
                                            </td>
                                            <td><span class="badge {{ $passenger->boarding === 'present' ? 'badge-success' : ($passenger->boarding === 'absent' ? 'badge-danger' : 'badge-info') }}">{{ $boarding[$passenger->boarding] ?? $passenger->boarding }}</span></td>
                                            @if($canAttendance)
                                                <td class="text-right">
                                                    <form method="post" action="{{ route('admin.events.transport.presence', [$event, $passenger]) }}" class="inline-form" style="gap:4px;justify-content:flex-end">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" name="boarding" value="present" class="btn btn-secondary btn-sm">Présent</button>
                                                        <button type="submit" name="boarding" value="absent" class="btn btn-ghost btn-sm">Absent</button>
                                                        <button type="submit" name="boarding" value="pending" class="btn btn-ghost btn-sm">Réinitialiser</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <x-admin.empty icon="users" title="Aucun passager" text="Aucun membre n’a encore réservé ce bus." />
        @endif
    </article>

    @if($canCommunicate)
        <article class="card card-flush">
            <div class="card-head"><div><h2>Message aux passagers</h2><div class="sub">Envoyé comme notification à tous les passagers de ce bus.</div></div></div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.events.transport.message', [$event, $bus]) }}" class="form-grid">
                    @csrf
                    <div class="field span-2"><label>Message <span class="req">*</span></label><textarea name="message" rows="3" maxlength="500" required placeholder="Départ avancé de 15 minutes, rendez-vous devant la gare…">{{ old('message') }}</textarea></div>
                    <div class="form-actions span-2"><button type="submit" class="btn btn-primary">Envoyer</button></div>
                </form>
            </div>
        </article>
    @endif
@endsection
