@extends('admin.layouts.app')

@section('title', 'Transport — '.$event->title)
@section('section', 'Événements')

@php
    $dt = fn ($value) => $value?->format('Y-m-d\TH:i');
    $hm = fn ($value) => $value?->format('d/m H:i');
    // Valeur d'un champ : l'ancienne saisie uniquement pour la modale qui vient d'échouer.
    $o = fn (string $form, string $key, $default = null) => old('_form') === $form ? old($key, $default) : $default;
@endphp

@section('content')
    @include('admin.events.manage._header')

    @if($full)
        @php
            $withStops = $buses->filter(fn ($bus) => $bus->stops->isNotEmpty())->count();
            $withManagers = $buses->filter(fn ($bus) => $bus->managers->isNotEmpty())->count();
            $steps = [
                ['Régions', $regions->count(), $regions->isNotEmpty()],
                ['Points de départ', $points->count(), $points->isNotEmpty()],
                ['Bus', $buses->count(), $buses->isNotEmpty()],
                ['Circuits définis', $withStops.' / '.$buses->count(), $buses->isNotEmpty() && $withStops === $buses->count()],
                ['Responsables', $withManagers.' / '.$buses->count(), $buses->isNotEmpty() && $withManagers === $buses->count()],
            ];
        @endphp

        <ol class="tr-steps" aria-label="Avancement de la préparation du transport">
            @foreach($steps as $index => [$label, $value, $done])
                <li class="tr-step {{ $done ? 'is-done' : '' }}">
                    <b>@if($done)<x-admin.icon name="check" :size="14" />@else{{ $index + 1 }}@endif</b>
                    <span>{{ $label }}<small>{{ $value }}</small></span>
                </li>
            @endforeach
        </ol>

        <div class="tr-kpis">
            <div><span>Inscrits actifs</span><strong>{{ $stats['registered'] }}</strong></div>
            <div><span>En bus</span><strong>{{ $stats['bus'] }}<small> / {{ $stats['seats'] }} places</small></strong></div>
            <div><span>Véhicule personnel</span><strong>{{ $stats['own'] }}</strong></div>
            <div><span>Sans choix</span><strong>{{ $stats['undecided'] }}</strong></div>
        </div>

        <div class="tabs" role="tablist" aria-label="Sections du transport" data-tabset style="margin-bottom:16px">
            <button type="button" role="tab" class="tab is-active" id="tab-btn-buses" aria-controls="pane-buses" aria-selected="true">Bus et circuits <span class="n">{{ $buses->count() }}</span></button>
            <button type="button" role="tab" class="tab" id="tab-btn-regions" aria-controls="pane-regions" aria-selected="false">Régions et points de départ <span class="n">{{ $regions->count() }}</span></button>
        </div>
    @endif

    {{-- ===================== Bus et circuits ===================== --}}
    <section class="tab-panel" id="pane-buses" role="tabpanel" aria-labelledby="tab-btn-buses">
        @if($full)
            <div class="tr-toolbar">
                <p class="text-sm text-muted">Chaque bus part d’un point de la liste, passe par ses points de collecte dans l’ordre, jusqu’à l’adresse de l’événement{{ $event->location ? ' ('.$event->location.')' : '' }}.</p>
                <button type="button" class="btn btn-primary" data-modal-open="bus-create"><x-admin.icon name="plus" :size="16" /> Ajouter un bus</button>
            </div>
        @endif

        @if($buses->isEmpty())
            <article class="card card-flush">
                <x-admin.empty icon="calendar" title="Aucun bus" :text="$full ? 'Ajoutez un bus, puis définissez son circuit et ses responsables.' : 'Vous n’êtes responsable d’aucun bus pour cet événement.'" />
            </article>
        @else
            <div class="tr-grid">
                @foreach($buses as $bus)
                    @php($percent = $bus->capacity > 0 ? min(100, (int) round($bus->taken_count / $bus->capacity * 100)) : 0)
                    <article class="card tr-bus" id="bus-{{ $bus->id }}">
                        <header class="tr-bus-head">
                            <div>
                                <h3>{{ $bus->name }}</h3>
                                @if($bus->plate || $bus->notes)<p class="text-sm text-muted">{{ collect([$bus->plate, $bus->notes])->filter()->implode(' · ') }}</p>@endif
                            </div>
                            <a href="{{ route('admin.events.transport.bus.show', [$event, $bus]) }}" class="btn btn-secondary btn-sm"><x-admin.icon name="users" :size="14" /> Passagers</a>
                        </header>

                        <div class="tr-meter-box">
                            <div class="tr-meter"><span style="width:{{ $percent }}%"></span></div>
                            <p class="text-sm"><strong>{{ $bus->taken_count }}</strong> / {{ $bus->capacity }} places réservées</p>
                        </div>

                        <h4 class="tr-sub">Circuit</h4>
                        <ol class="tr-route">
                            @forelse($bus->stops as $stop)
                                <li>
                                    <div class="tr-route-main">
                                        <strong>{{ $stop->point->label() }}@if($loop->first) <span class="badge badge-info">Départ</span>@endif</strong>
                                        <span class="text-sm text-muted">{{ $stop->point->region?->name }} · départ {{ $hm($stop->departs_at) ?? '—' }}@if($stop->arrives_at) · arrivée estimée {{ $hm($stop->arrives_at) }}@endif</span>
                                    </div>
                                    @if($full)
                                        <div class="tr-route-actions">
                                            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-modal-open="stop-edit-{{ $stop->id }}" aria-label="Modifier l’arrêt"><x-admin.icon name="edit" :size="14" /></button>
                                            <form method="post" action="{{ route('admin.events.transport.stops.destroy', [$event, $stop]) }}" data-confirm="Retirer « {{ $stop->point->name }} » du circuit de {{ $bus->name }} ?" data-confirm-title="Retirer l’arrêt">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Retirer l’arrêt"><x-admin.icon name="trash" :size="14" /></button>
                                            </form>
                                        </div>
                                    @endif
                                </li>
                            @empty
                                <li class="is-empty"><span class="text-sm text-muted">Aucun arrêt pour le moment.</span></li>
                            @endforelse
                            <li class="is-end"><div class="tr-route-main"><strong>{{ $event->location ?: 'Lieu de l’événement' }}</strong><span class="text-sm text-muted">Destination</span></div></li>
                        </ol>
                        @if($full)
                            <button type="button" class="btn btn-secondary btn-sm" data-modal-open="stop-create-{{ $bus->id }}"><x-admin.icon name="plus" :size="14" /> Ajouter un arrêt</button>
                        @endif

                        <h4 class="tr-sub">Responsables</h4>
                        <div class="tr-chips">
                            @forelse($bus->managers as $manager)
                                <span class="tr-chip">
                                    <span>{{ $manager->user?->name }}<small>{{ $manager->dutyLabel() }}</small></span>
                                    @if($full)
                                        <form method="post" action="{{ route('admin.events.transport.managers.destroy', [$event, $manager]) }}" data-confirm="Retirer {{ $manager->user?->name }} des responsables de {{ $bus->name }} ?" data-confirm-title="Retirer le responsable">
                                            @csrf @method('DELETE')
                                            <button type="submit" aria-label="Retirer">✕</button>
                                        </form>
                                    @endif
                                </span>
                            @empty
                                <span class="text-sm text-muted">Aucun responsable désigné.</span>
                            @endforelse
                        </div>
                        @if($full)
                            <button type="button" class="btn btn-secondary btn-sm" data-modal-open="manager-create-{{ $bus->id }}"><x-admin.icon name="plus" :size="14" /> Ajouter un responsable</button>
                        @endif

                        @if($full)
                            <footer class="tr-bus-foot">
                                <button type="button" class="btn btn-ghost btn-sm" data-modal-open="bus-edit-{{ $bus->id }}"><x-admin.icon name="edit" :size="14" /> Modifier le bus</button>
                                <form method="post" action="{{ route('admin.events.transport.bus.destroy', [$event, $bus]) }}" data-confirm="Supprimer « {{ $bus->name }} » et son circuit ?" data-confirm-title="Supprimer le bus">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm"><x-admin.icon name="trash" :size="14" /> Supprimer</button>
                                </form>
                            </footer>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    @if($full)
        {{-- ===================== Régions et points ===================== --}}
        <section class="tab-panel" id="pane-regions" role="tabpanel" aria-labelledby="tab-btn-regions" hidden>
            <div class="tr-toolbar">
                <p class="text-sm text-muted">Les membres choisissent leur région de départ à l’inscription, puis un bus et un point de cette région.</p>
                <button type="button" class="btn btn-primary" data-modal-open="region-create"><x-admin.icon name="plus" :size="16" /> Ajouter une région</button>
            </div>

            @forelse($regions as $region)
                <article class="card card-flush" style="margin-bottom:14px">
                    <div class="card-head">
                        <div>
                            <h2>{{ $region->name }}</h2>
                            <div class="sub">{{ $region->points->count() }} point(s) · {{ $regionStats[$region->id]['bus'] ?? 0 }} en bus · {{ $regionStats[$region->id]['own'] ?? 0 }} en véhicule personnel</div>
                        </div>
                        <div class="inline-form">
                            <button type="button" class="btn btn-secondary btn-sm" data-modal-open="point-create-{{ $region->id }}"><x-admin.icon name="plus" :size="14" /> Point de départ</button>
                            <form method="post" action="{{ route('admin.events.transport.regions.destroy', [$event, $region]) }}" data-confirm="Supprimer la région « {{ $region->name }} » ?" data-confirm-title="Supprimer la région">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Supprimer la région"><x-admin.icon name="trash" :size="14" /></button>
                            </form>
                        </div>
                    </div>
                    @forelse($region->points as $point)
                        <div class="list-item">
                            <div class="grow">
                                <strong>{{ $point->name }}</strong>
                                <div class="text-sm text-muted">{{ collect([$point->city, $point->address])->filter()->implode(' · ') }}</div>
                            </div>
                            @if($point->latitude !== null)<span class="badge badge-info">GPS</span>@endif
                            <span class="text-sm text-muted nowrap">{{ $point->stops->count() }} bus</span>
                            @if($url = $point->mapsUrl())<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-icon btn-sm" aria-label="Voir sur la carte"><x-admin.icon name="map" :size="14" /></a>@endif
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-modal-open="point-edit-{{ $point->id }}" aria-label="Modifier le point"><x-admin.icon name="edit" :size="14" /></button>
                            <form method="post" action="{{ route('admin.events.transport.points.destroy', [$event, $point]) }}" data-confirm="Supprimer le point « {{ $point->name }} » ?" data-confirm-title="Supprimer le point">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Supprimer le point"><x-admin.icon name="trash" :size="14" /></button>
                            </form>
                        </div>
                    @empty
                        <p class="card-body text-muted" style="padding-top:14px;padding-bottom:14px">Aucun point de départ dans cette région.</p>
                    @endforelse
                </article>
            @empty
                <article class="card card-flush"><x-admin.empty icon="map" title="Aucune région" text="Commencez par ajouter les régions de départ (Sfax, Sousse, Grand Tunis…)." /></article>
            @endforelse
        </section>

        {{-- ===================== Modales ===================== --}}
        <x-admin.modal id="region-create" title="Ajouter une région de départ" :action="route('admin.events.transport.regions.store', $event)" submit="Ajouter" icon="plus">
            <div class="field" style="grid-column:1/-1"><label>Nom de la région <span class="req">*</span></label><input name="name" required maxlength="120" value="{{ $o('region-create', 'name') }}" placeholder="Sfax, Sousse, Grand Tunis…"></div>
        </x-admin.modal>

        <x-admin.modal id="bus-create" title="Ajouter un bus" :action="route('admin.events.transport.bus.store', $event)" submit="Ajouter" icon="plus">
            <div class="field" style="grid-column:1/-1"><label>Nom du bus <span class="req">*</span></label><input name="name" required maxlength="120" value="{{ $o('bus-create', 'name') }}" placeholder="Bus 1 — Sud"></div>
            <div class="field"><label>Capacité (places) <span class="req">*</span></label><input type="number" name="capacity" min="1" max="500" required value="{{ $o('bus-create', 'capacity', 50) }}"></div>
            <div class="field"><label>Immatriculation</label><input name="plate" maxlength="40" value="{{ $o('bus-create', 'plate') }}"></div>
            <div class="field" style="grid-column:1/-1"><label>Notes</label><input name="notes" maxlength="500" value="{{ $o('bus-create', 'notes') }}" placeholder="Transporteur, contact chauffeur…"></div>

            <div class="field" style="grid-column:1/-1">
                <label>Point de départ du bus <span class="req">*</span></label>
                <select name="point_id" required>
                    <option value="">— choisir dans la liste —</option>
                    @foreach($regions as $region)
                        @if($region->points->isNotEmpty())
                            <optgroup label="{{ $region->name }}">
                                @foreach($region->points as $option)<option value="{{ $option->id }}" @selected((int) $o('bus-create', 'point_id') === $option->id)>{{ $option->label() }}</option>@endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
                @if($points->isEmpty())
                    <p class="hint">Aucun point disponible : créez d’abord une région et un point dans l’onglet « Régions et points de départ ».</p>
                @else
                    <p class="hint">Premier arrêt du circuit ; les points de collecte suivants s’ajoutent ensuite depuis la carte du bus.</p>
                @endif
            </div>
            <div class="field"><label>Heure de départ <span class="req">*</span></label><input type="datetime-local" name="departs_at" required value="{{ $o('bus-create', 'departs_at') }}"></div>
            <div class="field"><label>Arrivée estimée à l’événement</label><input type="datetime-local" name="arrives_at" value="{{ $o('bus-create', 'arrives_at') }}"></div>
        </x-admin.modal>

        @foreach($regions as $region)
            <x-admin.modal :id="'point-create-'.$region->id" :title="'Nouveau point de départ — '.$region->name" :action="route('admin.events.transport.points.store', $event)" submit="Ajouter" icon="plus">
                @include('admin.events.manage._transport_point_fields', ['formId' => 'point-create-'.$region->id, 'point' => null, 'region' => $region])
            </x-admin.modal>
        @endforeach

        @foreach($points as $point)
            <x-admin.modal :id="'point-edit-'.$point->id" :title="'Modifier le point — '.$point->name" :action="route('admin.events.transport.points.update', [$event, $point])" method="PUT">
                @include('admin.events.manage._transport_point_fields', ['formId' => 'point-edit-'.$point->id, 'point' => $point])
            </x-admin.modal>
        @endforeach

        @foreach($buses as $bus)
            <x-admin.modal :id="'bus-edit-'.$bus->id" :title="'Modifier '.$bus->name" :action="route('admin.events.transport.bus.update', [$event, $bus])" method="PUT">
                @php($form = 'bus-edit-'.$bus->id)
                <div class="field" style="grid-column:1/-1"><label>Nom du bus <span class="req">*</span></label><input name="name" required maxlength="120" value="{{ $o($form, 'name', $bus->name) }}"></div>
                <div class="field"><label>Capacité (places) <span class="req">*</span></label><input type="number" name="capacity" min="1" max="500" required value="{{ $o($form, 'capacity', $bus->capacity) }}"></div>
                <div class="field"><label>Immatriculation</label><input name="plate" maxlength="40" value="{{ $o($form, 'plate', $bus->plate) }}"></div>
                <div class="field" style="grid-column:1/-1"><label>Notes</label><input name="notes" maxlength="500" value="{{ $o($form, 'notes', $bus->notes) }}"></div>
            </x-admin.modal>

            <x-admin.modal :id="'stop-create-'.$bus->id" :title="'Ajouter un arrêt — '.$bus->name" :action="route('admin.events.transport.stops.store', [$event, $bus])" submit="Ajouter au circuit" icon="plus">
                @php($form = 'stop-create-'.$bus->id)
                <div class="field" style="grid-column:1/-1">
                    <label>Point de départ ou de collecte <span class="req">*</span></label>
                    <select name="point_id" required>
                        <option value="">— choisir —</option>
                        @foreach($regions as $region)
                            @if($region->points->isNotEmpty())
                                <optgroup label="{{ $region->name }}">
                                    @foreach($region->points as $point)<option value="{{ $point->id }}" @selected((int) $o($form, 'point_id') === $point->id)>{{ $point->label() }}</option>@endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    @if($points->isEmpty())<p class="hint">Aucun point disponible : créez-en d’abord dans « Régions et points de départ ».</p>@endif
                </div>
                <div class="field"><label>Heure de départ</label><input type="datetime-local" name="departs_at" value="{{ $o($form, 'departs_at') }}"></div>
                <div class="field"><label>Arrivée estimée à l’événement</label><input type="datetime-local" name="arrives_at" value="{{ $o($form, 'arrives_at') }}"></div>
                <p class="hint" style="grid-column:1/-1">Le point est ajouté à la fin du circuit ; vous pourrez changer l’ordre ensuite.</p>
            </x-admin.modal>

            <x-admin.modal :id="'manager-create-'.$bus->id" :title="'Ajouter un responsable — '.$bus->name" :action="route('admin.events.transport.managers.store', [$event, $bus])" submit="Ajouter" icon="plus">
                @include('admin.events.manage._member_picker', ['url' => route('admin.events.transport.search', $event)])
                <div class="field" style="grid-column:1/-1">
                    <label>Mission</label>
                    <select name="duty">@foreach($duties as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select>
                </div>
            </x-admin.modal>

            @foreach($bus->stops as $stop)
                <x-admin.modal :id="'stop-edit-'.$stop->id" :title="'Modifier l’arrêt — '.$stop->point->name" :action="route('admin.events.transport.stops.update', [$event, $stop])" method="PUT">
                    @php($form = 'stop-edit-'.$stop->id)
                    <div class="field"><label>Ordre dans le circuit <span class="req">*</span></label><input type="number" name="sort_order" min="1" max="999" required value="{{ $o($form, 'sort_order', $stop->sort_order) }}"></div>
                    <span></span>
                    <div class="field"><label>Heure de départ</label><input type="datetime-local" name="departs_at" value="{{ $o($form, 'departs_at', $dt($stop->departs_at)) }}"></div>
                    <div class="field"><label>Arrivée estimée à l’événement</label><input type="datetime-local" name="arrives_at" value="{{ $o($form, 'arrives_at', $dt($stop->arrives_at)) }}"></div>
                </x-admin.modal>
            @endforeach
        @endforeach

        @push('head')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        @endpush
        @push('scripts')
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
            <script>
                // Choix d'un point GPS : clic sur la carte, repère déplaçable, recherche d'un lieu (OpenStreetMap / Nominatim) ou position de l'appareil.
                (function () {
                    var mapElements = document.querySelectorAll('[data-geo-picker]');
                    if (!mapElements.length) { return; }

                    if (typeof L === 'undefined') {
                        mapElements.forEach(function (box) {
                            box.querySelector('[data-geo-map]').textContent = 'La carte n’a pas pu être chargée : saisissez la latitude et la longitude à la main.';
                        });
                        return;
                    }

                    mapElements.forEach(function (box) {
                        var form = box.closest('form');
                        var lat = form.querySelector('[name="latitude"]');
                        var lng = form.querySelector('[name="longitude"]');
                        var mapEl = box.querySelector('[data-geo-map]');
                        var query = box.querySelector('[data-geo-query]');
                        var results = box.querySelector('[data-geo-results]');
                        var map = null;
                        var marker = null;

                        function current() {
                            var a = parseFloat(lat.value);
                            var b = parseFloat(lng.value);
                            return isFinite(a) && isFinite(b) ? [a, b] : null;
                        }

                        function write(a, b) {
                            lat.value = a.toFixed(7);
                            lng.value = b.toFixed(7);
                        }

                        function place(latlng, zoomIn) {
                            if (!marker) {
                                marker = L.marker(latlng, { draggable: true }).addTo(map);
                                marker.on('dragend', function () {
                                    var p = marker.getLatLng();
                                    write(p.lat, p.lng);
                                });
                            } else {
                                marker.setLatLng(latlng);
                            }
                            if (zoomIn) { map.setView(latlng, Math.max(map.getZoom(), 15)); }
                        }

                        function init() {
                            var start = current();
                            map = L.map(mapEl).setView(start || [34.0, 9.5], start ? 15 : 6);
                            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 19,
                                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>'
                            }).addTo(map);
                            if (start) { place(start, false); }
                            map.on('click', function (event) {
                                write(event.latlng.lat, event.latlng.lng);
                                place(event.latlng, false);
                            });
                        }

                        // La carte est créée quand la modale devient visible (taille non nulle), puis recalculée si elle change.
                        new ResizeObserver(function () {
                            if (mapEl.clientWidth === 0) { return; }
                            if (!map) { init(); } else { map.invalidateSize(); }
                        }).observe(mapEl);

                        [lat, lng].forEach(function (input) {
                            input.addEventListener('change', function () {
                                var value = current();
                                if (!map) { return; }
                                if (value) { place(value, true); } else if (marker) { map.removeLayer(marker); marker = null; }
                            });
                        });

                        box.querySelector('[data-geo-clear]').addEventListener('click', function () {
                            lat.value = '';
                            lng.value = '';
                            if (marker && map) { map.removeLayer(marker); marker = null; }
                        });

                        box.querySelector('[data-geo-locate]').addEventListener('click', function () {
                            if (!navigator.geolocation || !map) { return; }
                            navigator.geolocation.getCurrentPosition(function (position) {
                                write(position.coords.latitude, position.coords.longitude);
                                place([position.coords.latitude, position.coords.longitude], true);
                            });
                        });

                        function showResults(items) {
                            results.innerHTML = '';
                            if (!items.length) {
                                var none = document.createElement('li');
                                none.textContent = 'Aucun lieu trouvé.';
                                results.appendChild(none);
                            }
                            items.forEach(function (item) {
                                var li = document.createElement('li');
                                li.setAttribute('role', 'option');
                                li.textContent = item.display_name;
                                li.addEventListener('click', function () {
                                    var a = parseFloat(item.lat);
                                    var b = parseFloat(item.lon);
                                    write(a, b);
                                    place([a, b], true);
                                    var address = item.address || {};
                                    var city = form.querySelector('[name="city"]');
                                    var street = form.querySelector('[name="address"]');
                                    if (city && !city.value) { city.value = address.city || address.town || address.village || address.municipality || address.county || ''; }
                                    if (street && !street.value) { street.value = item.display_name.slice(0, 255); }
                                    results.hidden = true;
                                });
                                results.appendChild(li);
                            });
                            results.hidden = false;
                        }

                        function search() {
                            var term = query.value.trim();
                            if (term.length < 3) { return; }
                            fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=6&accept-language=fr&q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } })
                                .then(function (response) { return response.ok ? response.json() : []; })
                                .then(showResults)
                                .catch(function () { showResults([]); });
                        }

                        box.querySelector('[data-geo-search]').addEventListener('click', search);
                        query.addEventListener('keydown', function (event) {
                            if (event.key === 'Enter') { event.preventDefault(); search(); }
                        });
                    });
                })();
            </script>
        @endpush
    @endif
@endsection
