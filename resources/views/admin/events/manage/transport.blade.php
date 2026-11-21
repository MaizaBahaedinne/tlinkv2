@extends('admin.layouts.app')

@section('title', 'Transport — '.$event->title)
@section('section', 'Événements')

@php
    $dt = fn ($value) => $value?->format('Y-m-d\TH:i');
    $hm = fn ($value) => $value?->format('d/m H:i') ?? '—';
@endphp

@section('content')
    @include('admin.events.manage._header')

    @if($full)
        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head"><h2>Vue d’ensemble</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <div><dt>Inscrits actifs</dt><dd>{{ $stats['registered'] }}</dd></div>
                    <div><dt>En bus</dt><dd>{{ $stats['bus'] }} / {{ $stats['seats'] }} places</dd></div>
                    <div><dt>Départ motorisé personnel</dt><dd>{{ $stats['own'] }}</dd></div>
                    <div><dt>Sans choix de transport</dt><dd>{{ $stats['undecided'] }}</dd></div>
                </dl>
            </div>
        </article>

        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head">
                <div>
                    <h2>1. Régions de départ</h2>
                    <div class="sub">Les membres choisissent leur région de départ à l’inscription.</div>
                </div>
            </div>
            @if($regions->count())
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>Région</th><th>Points</th><th>En bus</th><th>Véhicule perso</th><th></th></tr></thead>
                        <tbody>
                            @foreach($regions as $region)
                                <tr>
                                    <td class="cell-main">{{ $region->name }}</td>
                                    <td>{{ $region->points->count() }}</td>
                                    <td>{{ $regionStats[$region->id]['bus'] ?? 0 }}</td>
                                    <td>{{ $regionStats[$region->id]['own'] ?? 0 }}</td>
                                    <td class="text-right">
                                        <form method="post" action="{{ route('admin.events.transport.regions.destroy', [$event, $region]) }}" onsubmit="return confirm('Supprimer cette région ?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="card-body">
                <form method="post" action="{{ route('admin.events.transport.regions.store', $event) }}" class="inline-form" style="flex-wrap:wrap;gap:8px">
                    @csrf
                    <input name="name" required maxlength="120" placeholder="Nouvelle région (Sfax, Sousse, Grand Tunis…)" aria-label="Nom de la région" value="{{ old('name') }}">
                    <button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter la région</button>
                </form>
            </div>
        </article>

        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head">
                <div>
                    <h2>2. Points de départ / collecte</h2>
                    <div class="sub">Région, ville et position GPS. Les heures se règlent bus par bus dans le circuit.</div>
                </div>
            </div>
            @foreach($points as $point)
                <details style="padding:0 16px;margin-bottom:6px">
                    <summary>
                        <strong>{{ $point->name }}</strong> — {{ $point->city }} <span class="text-muted">({{ $point->region?->name }})</span>
                        @if($point->latitude !== null)<span class="badge badge-info">GPS</span>@endif
                        <span class="text-muted text-sm">· {{ $point->stops->count() }} bus</span>
                    </summary>
                    <div style="padding:10px 0">
                        <form method="post" action="{{ route('admin.events.transport.points.update', [$event, $point]) }}" class="form-grid">
                            @csrf @method('PUT')
                            @include('admin.events.manage._transport_point_fields', ['point' => $point])
                            <div class="form-actions span-2"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                        </form>
                        <div style="display:flex;gap:10px;align-items:center;margin-top:8px">
                            @if($url = $point->mapsUrl())<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">Voir sur la carte</a>@endif
                            <form method="post" action="{{ route('admin.events.transport.points.destroy', [$event, $point]) }}" onsubmit="return confirm('Supprimer ce point ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Supprimer le point</button>
                            </form>
                        </div>
                    </div>
                </details>
            @endforeach
            <div class="card-body">
                @if($regions->isEmpty())
                    <p class="text-muted">Ajoutez d’abord au moins une région de départ.</p>
                @else
                    <form method="post" action="{{ route('admin.events.transport.points.store', $event) }}" class="form-grid">
                        @csrf
                        @include('admin.events.manage._transport_point_fields', ['point' => null])
                        <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter le point</button></div>
                    </form>
                @endif
            </div>
        </article>

        <article class="card card-flush" style="margin-bottom:16px">
            <div class="card-head"><h2>3. Ajouter un bus</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.events.transport.bus.store', $event) }}" class="form-grid">
                    @csrf
                    <div class="field"><label>Nom du bus <span class="req">*</span></label><input name="name" required maxlength="120" placeholder="Bus 1 — Sud" value="{{ old('name') }}"></div>
                    <div class="field"><label>Immatriculation</label><input name="plate" maxlength="40" value="{{ old('plate') }}"></div>
                    <div class="field"><label>Capacité (places) <span class="req">*</span></label><input type="number" name="capacity" min="1" max="500" required value="{{ old('capacity', 50) }}"></div>
                    <div class="field"><label>Notes</label><input name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Transporteur, contact chauffeur…"></div>
                    <div class="form-actions span-2"><button type="submit" class="btn btn-primary"><x-admin.icon name="plus" :size="16" /> Ajouter le bus</button></div>
                </form>
            </div>
        </article>
    @endif

    @forelse($buses as $bus)
        <article class="card card-flush" id="bus-{{ $bus->id }}" style="margin-bottom:16px">
            <div class="card-head">
                <div>
                    <h2>{{ $bus->name }}@if($bus->plate) <span class="text-muted text-sm">({{ $bus->plate }})</span>@endif</h2>
                    <div class="sub">{{ $bus->taken_count }} / {{ $bus->capacity }} places réservées</div>
                </div>
                <a href="{{ route('admin.events.transport.bus.show', [$event, $bus]) }}" class="btn btn-secondary btn-sm">Passagers, présence et messages</a>
            </div>

            <div class="card-body">
                <h3 style="margin:0 0 8px">Circuit</h3>
                @if($bus->stops->count())
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>#</th><th>Point</th><th>Région</th><th>Départ</th><th>Arrivée estimée</th>@if($full)<th></th>@endif</tr></thead>
                            <tbody>
                                @foreach($bus->stops as $stop)
                                    <tr>
                                        @if($full)
                                            <td colspan="5">
                                                <form method="post" action="{{ route('admin.events.transport.stops.update', [$event, $stop]) }}" class="inline-form" style="flex-wrap:wrap;gap:6px;align-items:center">
                                                    @csrf @method('PUT')
                                                    <input type="number" name="sort_order" min="1" max="999" value="{{ $stop->sort_order }}" style="width:70px" aria-label="Ordre" required>
                                                    <strong>{{ $stop->point->label() }}</strong>
                                                    <span class="text-muted">{{ $stop->point->region?->name }}</span>
                                                    <input type="datetime-local" name="departs_at" value="{{ $dt($stop->departs_at) }}" aria-label="Heure de départ">
                                                    <input type="datetime-local" name="arrives_at" value="{{ $dt($stop->arrives_at) }}" aria-label="Arrivée estimée à l’événement">
                                                    <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
                                                </form>
                                            </td>
                                            <td class="text-right">
                                                <form method="post" action="{{ route('admin.events.transport.stops.destroy', [$event, $stop]) }}" onsubmit="return confirm('Retirer cet arrêt du circuit ?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /></button>
                                                </form>
                                            </td>
                                        @else
                                            <td>{{ $stop->sort_order }}</td>
                                            <td class="cell-main">{{ $stop->point->label() }}</td>
                                            <td>{{ $stop->point->region?->name }}</td>
                                            <td>{{ $hm($stop->departs_at) }}</td>
                                            <td>{{ $hm($stop->arrives_at) }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="hint">Le bus passe par ces points dans l’ordre, puis rejoint l’adresse de l’événement{{ $event->location ? ' ('.$event->location.')' : '' }}.</p>
                @else
                    <p class="text-muted">Aucun arrêt : ajoutez le point de départ puis les points de collecte.</p>
                @endif

                @if($full)
                    <form method="post" action="{{ route('admin.events.transport.stops.store', [$event, $bus]) }}" class="inline-form" style="flex-wrap:wrap;gap:8px;margin-top:10px;align-items:flex-end">
                        @csrf
                        <label class="text-sm">Point
                            <select name="point_id" required>
                                <option value="">— choisir —</option>
                                @foreach($points as $point)<option value="{{ $point->id }}">{{ $point->region?->name }} · {{ $point->label() }}</option>@endforeach
                            </select>
                        </label>
                        <label class="text-sm">Départ <input type="datetime-local" name="departs_at"></label>
                        <label class="text-sm">Arrivée estimée <input type="datetime-local" name="arrives_at"></label>
                        <button type="submit" class="btn btn-secondary"><x-admin.icon name="plus" :size="16" /> Ajouter au circuit</button>
                    </form>
                @endif

                <h3 style="margin:18px 0 8px">Responsables (présence et communication)</h3>
                @forelse($bus->managers as $manager)
                    <div style="display:flex;justify-content:space-between;gap:8px;padding:4px 0;align-items:center">
                        <span>{{ $manager->user?->name }} <span class="text-sm text-muted">· {{ $manager->dutyLabel() }}</span></span>
                        @if($full)
                            <form method="post" action="{{ route('admin.events.transport.managers.destroy', [$event, $manager]) }}" onsubmit="return confirm('Retirer ce responsable ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Retirer</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted">Aucun responsable : désignez au moins une personne.</p>
                @endforelse

                @if($full)
                    <form method="post" action="{{ route('admin.events.transport.managers.store', [$event, $bus]) }}" class="form-grid" style="margin-top:10px">
                        @csrf
                        @include('admin.events.manage._member_picker', ['url' => route('admin.events.transport.search', $event)])
                        <div class="field">
                            <label>Mission</label>
                            <select name="duty">@foreach($duties as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select>
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn-secondary"><x-admin.icon name="plus" :size="16" /> Ajouter le responsable</button></div>
                    </form>

                    <details style="margin-top:16px">
                        <summary>Modifier ou supprimer le bus</summary>
                        <form method="post" action="{{ route('admin.events.transport.bus.update', [$event, $bus]) }}" class="form-grid" style="margin-top:8px">
                            @csrf @method('PUT')
                            <div class="field"><label>Nom</label><input name="name" required maxlength="120" value="{{ $bus->name }}"></div>
                            <div class="field"><label>Immatriculation</label><input name="plate" maxlength="40" value="{{ $bus->plate }}"></div>
                            <div class="field"><label>Capacité</label><input type="number" name="capacity" min="1" max="500" required value="{{ $bus->capacity }}"></div>
                            <div class="field"><label>Notes</label><input name="notes" maxlength="500" value="{{ $bus->notes }}"></div>
                            <div class="form-actions span-2"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                        </form>
                        <form method="post" action="{{ route('admin.events.transport.bus.destroy', [$event, $bus]) }}" onsubmit="return confirm('Supprimer ce bus ?')" style="margin-top:6px">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Supprimer le bus</button>
                        </form>
                    </details>
                @endif
            </div>
        </article>
    @empty
        <article class="card card-flush">
            <x-admin.empty icon="calendar" title="Aucun bus" text="{{ $full ? 'Ajoutez un bus, définissez son circuit et ses responsables.' : 'Vous n’êtes responsable d’aucun bus pour cet événement.' }}" />
        </article>
    @endforelse
@endsection
