{{-- Transport de l'inscrit : région de départ, puis véhicule personnel ou bus. Variables : $event, $transport, $input, $btn. --}}
@php
    $booking = $transport['booking'];
    $selectedRegion = (int) old('region_id', $booking?->region_id);
    $selectedMode = old('mode', $booking?->mode ?? 'bus');
    $selectedStop = (int) old('stop_id', $booking?->stop_id);
    $locked = $event->starts_at?->isPast() || ($booking && $booking->boarding !== 'pending');
@endphp

<div class="rounded-2xl border border-slate-200 p-4" id="transport">
    <h3 class="font-semibold text-slate-900">Transport</h3>

    @if($booking)
        <div class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">
            <p>Région de départ : <strong>{{ $booking->region?->name }}</strong></p>
            @if($booking->mode === 'bus' && $booking->stop)
                @php($point = $booking->stop->point)
                <p class="mt-1">
                    <strong>{{ $booking->bus?->name }}</strong> — départ de <strong>{{ $point->label() }}</strong>
                    @if($booking->stop->departs_at) le {{ $booking->stop->departs_at->translatedFormat('d/m à H:i') }}@endif
                    @if($booking->stop->arrives_at) · arrivée estimée {{ $booking->stop->arrives_at->format('H:i') }}@endif
                    @if($url = $point->mapsUrl()) · <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-tlink-blue underline">Itinéraire</a>@endif
                </p>
                @if($booking->bus?->managers->isNotEmpty())
                    <p class="mt-1 text-xs text-slate-600">Responsable(s) du bus : {{ $booking->bus->managers->map(fn ($manager) => $manager->user?->name)->filter()->implode(', ') }}</p>
                @endif
            @else
                <p class="mt-1">Vous venez avec votre propre véhicule (non pris en charge par l’organisation).</p>
            @endif
        </div>
    @else
        <p class="mt-1 text-sm text-slate-600">Indiquez votre région de départ puis comment vous viendrez.</p>
    @endif

    @if($locked)
        <p class="mt-2 text-xs text-slate-500">Le transport ne peut plus être modifié.</p>
    @else
        <form method="post" action="{{ route('member.events.transport', $event) }}" class="mt-3 grid gap-3 sm:grid-cols-2" id="transport-form">
            @csrf
            <label class="text-xs font-semibold text-slate-600 sm:col-span-2">Région de départ
                <select name="region_id" id="transport-region" class="{{ $input }} mt-1" required>
                    <option value="">— choisir —</option>
                    @foreach($transport['regions'] as $region)
                        <option value="{{ $region->id }}" @selected($selectedRegion === $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </label>

            <fieldset class="sm:col-span-2 text-sm text-slate-700">
                <legend class="text-xs font-semibold text-slate-600">Mode de transport</legend>
                @foreach(\App\Models\EventTransportBooking::MODES as $code => $label)
                    <label class="mt-1 flex items-center gap-2"><input type="radio" name="mode" value="{{ $code }}" @checked($selectedMode === $code)> {{ $label }}</label>
                @endforeach
            </fieldset>

            <label class="text-xs font-semibold text-slate-600 sm:col-span-2" id="transport-stop-box">Bus et point de départ
                <select name="stop_id" id="transport-stop" class="{{ $input }} mt-1">
                    <option value="" data-region="">— choisir —</option>
                    @foreach($transport['stops'] as $stop)
                        @php($full = $stop->bus->remaining_seats <= 0 && $stop->id !== $selectedStop)
                        <option value="{{ $stop->id }}" data-region="{{ $stop->point->region_id }}" @selected($selectedStop === $stop->id) @disabled($full)>
                            {{ $stop->bus->name }} · {{ $stop->point->label() }}{{ $stop->departs_at ? ' · départ '.$stop->departs_at->format('d/m H:i') : '' }}{{ $stop->arrives_at ? ' · arrivée '.$stop->arrives_at->format('H:i') : '' }} · {{ $full ? 'complet' : $stop->bus->remaining_seats.' place(s)' }}
                        </option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs font-normal text-slate-500" id="transport-stop-empty" hidden>Aucun bus ne part de cette région pour le moment.</span>
            </label>

            <div class="sm:col-span-2"><button type="submit" class="{{ $btn }}">Enregistrer mon transport</button></div>
        </form>

        <script>
            (function () {
                var form = document.getElementById('transport-form');
                if (!form) { return; }
                var region = document.getElementById('transport-region');
                var stop = document.getElementById('transport-stop');
                var box = document.getElementById('transport-stop-box');
                var empty = document.getElementById('transport-stop-empty');

                function sync() {
                    var bus = form.querySelector('input[name="mode"]:checked');
                    var useBus = bus && bus.value === 'bus';
                    box.hidden = !useBus;
                    var visible = 0;
                    Array.prototype.forEach.call(stop.options, function (option) {
                        if (option.value === '') { return; }
                        var match = option.dataset.region === region.value;
                        option.hidden = !match;
                        if (match) { visible++; }
                        if (!match && option.selected) { stop.value = ''; }
                    });
                    empty.hidden = !(useBus && region.value !== '' && visible === 0);
                }

                region.addEventListener('change', sync);
                form.querySelectorAll('input[name="mode"]').forEach(function (radio) { radio.addEventListener('change', sync); });
                sync();
            })();
        </script>
    @endif
</div>
