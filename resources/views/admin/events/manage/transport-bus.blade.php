@extends('admin.layouts.app')

@section('title', $bus->name.' — Transport — '.$event->title)
@section('section', 'Événements')

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

    <article class="card card-flush" style="margin-bottom:16px">
        <div class="card-head"><h2>Présence</h2></div>
        @if($passengers->count())
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>Passager</th><th>Point de montée</th><th>Statut</th>@if($canAttendance)<th></th>@endif</tr></thead>
                    <tbody>
                        @foreach($passengers as $passenger)
                            <tr>
                                <td><div class="cell-main">{{ $passenger->user?->name }}</div></td>
                                <td>{{ $passenger->stop?->point->label() }}<div class="cell-sub">{{ $passenger->stop?->departs_at?->format('d/m H:i') }}</div></td>
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
