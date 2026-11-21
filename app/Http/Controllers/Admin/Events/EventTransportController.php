<?php

namespace App\Http\Controllers\Admin\Events;

use App\Models\AppNotification;
use App\Models\EventBus;
use App\Models\EventBusManager;
use App\Models\EventBusStop;
use App\Models\EventRegistration;
use App\Models\EventTransportBooking;
use App\Models\EventTransportPoint;
use App\Models\EventTransportRegion;
use App\Models\FoundationEvent;
use App\Models\User;
use App\Support\Events\EventAccess;
use App\Support\Events\EventTransport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Gestion du transport d'un événement : régions de départ, points de départ / collecte (GPS), bus et circuits,
 * responsables de bus (présence et communication). Les responsables de bus n'accèdent qu'à leurs bus.
 */
class EventTransportController extends EventManageController
{
    public function index(Request $request, FoundationEvent $event): View
    {
        $user = $request->user();
        $full = EventAccess::can($user, $event, 'transport');

        abort_unless($full || EventAccess::can($user, $event, 'transport_bus'), 403, 'Votre rôle dans l’équipe de cet événement ne couvre pas cette action.');

        $buses = $event->buses()
            ->with(['stops.point.region', 'managers.user:id,name,email'])
            ->when(! $full, fn ($query) => $query->whereHas('managers', fn ($manager) => $manager->where('user_id', $user->id)))
            ->get();

        $buses->each(fn (EventBus $bus) => $bus->setAttribute('taken_count', $bus->taken()));

        $data = ['full' => $full, 'buses' => $buses, 'duties' => EventBusManager::DUTIES];

        if ($full) {
            $active = EventRegistration::query()->where('event_id', $event->id)->whereIn('status', ['pending', 'confirmed', 'attended']);
            $bookings = EventTransportBooking::query()->where('event_id', $event->id)->active();

            $regions = $event->transportRegions()->with('points')->get();
            $perRegion = (clone $bookings)->get()->groupBy('region_id');

            $data += [
                'regions' => $regions,
                'points' => $event->transportPoints()->with(['region', 'stops'])->orderBy('region_id')->orderBy('city')->orderBy('name')->get(),
                'regionStats' => $regions->mapWithKeys(fn (EventTransportRegion $region) => [$region->id => [
                    'bus' => $perRegion->get($region->id, collect())->where('mode', 'bus')->count(),
                    'own' => $perRegion->get($region->id, collect())->where('mode', 'own')->count(),
                ]]),
                'stats' => [
                    'registered' => (clone $active)->count(),
                    'bus' => (clone $bookings)->where('mode', 'bus')->count(),
                    'own' => (clone $bookings)->where('mode', 'own')->count(),
                    'seats' => $event->buses()->sum('capacity'),
                ],
            ];
            $data['stats']['undecided'] = max(0, $data['stats']['registered'] - $data['stats']['bus'] - $data['stats']['own']);
        }

        return $this->render($request, $event, 'transport', 'admin.events.manage.transport', $data);
    }

    /** Passagers d'un bus : pointage de présence et message aux passagers. */
    public function bus(Request $request, FoundationEvent $event, EventBus $bus): View
    {
        $this->ownBus($event, $bus);
        abort_unless($this->canDuty($request, $event, $bus, 'any'), 403, 'Vous n’êtes pas responsable de ce bus.');

        $bus->load(['stops.point.region', 'managers.user:id,name']);

        $passengers = $bus->bookings()->active()
            ->with(['user:id,name,email', 'stop.point', 'region'])
            ->get()
            ->sortBy(fn (EventTransportBooking $booking) => [$booking->stop?->sort_order ?? 999, $booking->user?->name])
            ->values();

        return $this->render($request, $event, 'transport', 'admin.events.manage.transport-bus', [
            'bus' => $bus,
            'passengers' => $passengers,
            'canAttendance' => $this->canDuty($request, $event, $bus, 'attendance'),
            'canCommunicate' => $this->canDuty($request, $event, $bus, 'communication'),
            'boarding' => EventTransportBooking::BOARDING,
        ]);
    }

    public function presence(Request $request, FoundationEvent $event, EventTransportBooking $booking): RedirectResponse
    {
        abort_unless($booking->event_id === $event->id && $booking->bus_id !== null, 404);
        $bus = EventBus::query()->findOrFail($booking->bus_id);
        abort_unless($this->canDuty($request, $event, $bus, 'attendance'), 403, 'Vous ne gérez pas la présence de ce bus.');

        $data = $request->validate(['boarding' => ['required', Rule::in(array_keys(EventTransportBooking::BOARDING))]]);

        $booking->update(['boarding' => $data['boarding'], 'marked_by' => $data['boarding'] === 'pending' ? null : $request->user()->id]);

        return back()->with('status', 'Présence mise à jour.');
    }

    public function message(Request $request, FoundationEvent $event, EventBus $bus, EventTransport $transport): RedirectResponse
    {
        $this->ownBus($event, $bus);
        abort_unless($this->canDuty($request, $event, $bus, 'communication'), 403, 'Vous ne gérez pas la communication de ce bus.');

        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);

        $count = $transport->notifyPassengers($bus, 'Transport — '.$bus->name, $data['message']);
        $this->manageAudit($request, $event, 'transport.message:'.$bus->id);

        return back()->with('status', $count > 0 ? 'Message envoyé à '.$count.' passager(s).' : 'Aucun passager inscrit sur ce bus pour le moment.');
    }

    // --- Régions de départ -------------------------------------------------------------------------------------------

    public function storeRegion(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'transport');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('event_transport_regions', 'name')->where('event_id', $event->id)],
        ], ['name.unique' => 'Cette région existe déjà.']);

        $event->transportRegions()->create($data + ['sort_order' => (int) $event->transportRegions()->max('sort_order') + 1]);
        $this->manageAudit($request, $event, 'transport.region.created');

        return back()->with('status', 'Région ajoutée.');
    }

    public function destroyRegion(Request $request, FoundationEvent $event, EventTransportRegion $region): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        abort_unless($region->event_id === $event->id, 404);

        if ($region->points()->exists() || $region->bookings()->exists()) {
            return back()->with('error', 'Cette région a des points de départ ou des choix de membres : supprimez-les d’abord.');
        }

        $region->delete();
        $this->manageAudit($request, $event, 'transport.region.deleted');

        return back()->with('status', 'Région supprimée.');
    }

    // --- Points de départ / collecte ---------------------------------------------------------------------------------

    public function storePoint(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'transport');

        $event->transportPoints()->create($this->pointData($request, $event));
        $this->manageAudit($request, $event, 'transport.point.created');

        return back()->with('status', 'Point de départ ajouté.');
    }

    public function updatePoint(Request $request, FoundationEvent $event, EventTransportPoint $point): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        abort_unless($point->event_id === $event->id, 404);

        $point->update($this->pointData($request, $event));
        $this->manageAudit($request, $event, 'transport.point.updated');

        return back()->with('status', 'Point de départ mis à jour.');
    }

    public function destroyPoint(Request $request, FoundationEvent $event, EventTransportPoint $point): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        abort_unless($point->event_id === $event->id, 404);

        if ($point->stops()->exists()) {
            return back()->with('error', 'Ce point est utilisé dans le circuit d’un bus : retirez-le d’abord du circuit.');
        }

        $point->delete();
        $this->manageAudit($request, $event, 'transport.point.deleted');

        return back()->with('status', 'Point de départ supprimé.');
    }

    // --- Bus ---------------------------------------------------------------------------------------------------------

    public function storeBus(Request $request, FoundationEvent $event): RedirectResponse
    {
        $this->allow($request, $event, 'transport');

        $bus = $event->buses()->create($this->busData($request) + ['sort_order' => (int) $event->buses()->max('sort_order') + 1]);
        $this->manageAudit($request, $event, 'transport.bus.created');

        return redirect()->to(route('admin.events.transport.index', $event).'#bus-'.$bus->id)->with('status', 'Bus ajouté : définissez maintenant son circuit et ses responsables.');
    }

    public function updateBus(Request $request, FoundationEvent $event, EventBus $bus): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        $this->ownBus($event, $bus);

        $data = $this->busData($request);

        if ($data['capacity'] < $bus->taken()) {
            return back()->with('error', 'La capacité ne peut pas être inférieure aux places déjà réservées ('.$bus->taken().').');
        }

        $bus->update($data);
        $this->manageAudit($request, $event, 'transport.bus.updated');

        return back()->with('status', 'Bus mis à jour.');
    }

    public function destroyBus(Request $request, FoundationEvent $event, EventBus $bus): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        $this->ownBus($event, $bus);

        if ($bus->bookings()->exists()) {
            return back()->with('error', 'Des membres ont réservé ce bus : impossible de le supprimer.');
        }

        $bus->delete();
        $this->manageAudit($request, $event, 'transport.bus.deleted');

        return back()->with('status', 'Bus supprimé.');
    }

    // --- Circuit (points de collecte jusqu'à l'événement) -------------------------------------------------------------

    public function storeStop(Request $request, FoundationEvent $event, EventBus $bus): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        $this->ownBus($event, $bus);

        $data = $this->stopData($request, $event);

        if ($bus->stops()->where('point_id', $data['point_id'])->exists()) {
            return back()->withInput()->with('error', 'Ce point fait déjà partie du circuit de ce bus.');
        }

        $bus->stops()->create($data + ['sort_order' => (int) $bus->stops()->max('sort_order') + 1]);
        $this->manageAudit($request, $event, 'transport.stop.created');

        return back()->with('status', 'Arrêt ajouté au circuit.');
    }

    public function updateStop(Request $request, FoundationEvent $event, EventBusStop $stop): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        $stop->loadMissing('bus');
        $this->ownBus($event, $stop->bus);

        $data = $request->validate([
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
            'departs_at' => ['nullable', 'date'],
            'arrives_at' => ['nullable', 'date', 'after_or_equal:departs_at'],
        ]);

        $stop->update($data);
        $this->manageAudit($request, $event, 'transport.stop.updated');

        return back()->with('status', 'Arrêt mis à jour.');
    }

    public function destroyStop(Request $request, FoundationEvent $event, EventBusStop $stop): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        $stop->loadMissing('bus');
        $this->ownBus($event, $stop->bus);

        if ($stop->bookings()->exists()) {
            return back()->with('error', 'Des membres montent à cet arrêt : impossible de le retirer du circuit.');
        }

        $stop->delete();
        $this->manageAudit($request, $event, 'transport.stop.deleted');

        return back()->with('status', 'Arrêt retiré du circuit.');
    }

    // --- Responsables de bus -----------------------------------------------------------------------------------------

    public function storeManager(Request $request, FoundationEvent $event, EventBus $bus): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        $this->ownBus($event, $bus);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'duty' => ['required', Rule::in(array_keys(EventBusManager::DUTIES))],
        ], ['user_id.required' => 'Recherchez et choisissez un membre dans la liste.']);

        $manager = EventBusManager::query()->firstOrNew(['bus_id' => $bus->id, 'user_id' => $data['user_id']]);
        $isNew = ! $manager->exists;
        $manager->duty = $data['duty'];
        $manager->save();

        if ($isNew) {
            AppNotification::query()->create([
                'user_id' => $data['user_id'],
                'type' => 'event.transport',
                'title' => 'Responsable de bus',
                'body' => 'Vous êtes responsable du bus « '.$bus->name.' » ('.$manager->dutyLabel().') pour « '.$event->title.' ».',
                'data' => ['event_id' => $event->id, 'bus_id' => $bus->id],
            ]);
        }

        $this->manageAudit($request, $event, 'transport.manager.saved');

        return back()->with('status', 'Responsable enregistré.');
    }

    public function destroyManager(Request $request, FoundationEvent $event, EventBusManager $manager): RedirectResponse
    {
        $this->allow($request, $event, 'transport');
        $manager->loadMissing('bus');
        $this->ownBus($event, $manager->bus);

        $manager->delete();
        $this->manageAudit($request, $event, 'transport.manager.removed');

        return back()->with('status', 'Responsable retiré.');
    }

    /** Recherche d'un membre (nom ou e-mail) pour le désigner responsable d'un bus. */
    public function search(Request $request, FoundationEvent $event): JsonResponse
    {
        $this->allow($request, $event, 'transport');

        $validated = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $term = trim((string) ($validated['q'] ?? ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['members' => []]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $members = User::query()
            ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);

        return response()->json(['members' => $members]);
    }

    // --- Helpers -----------------------------------------------------------------------------------------------------

    private function ownBus(FoundationEvent $event, EventBus $bus): void
    {
        abort_unless($bus->event_id === $event->id, 404);
    }

    /** Droit sur un bus : gestionnaire du transport, ou responsable du bus avec la mission voulue. */
    private function canDuty(Request $request, FoundationEvent $event, EventBus $bus, string $duty): bool
    {
        $user = $request->user();

        if (EventAccess::can($user, $event, 'transport')) {
            return true;
        }

        return $bus->managers()
            ->where('user_id', $user->id)
            ->when($duty !== 'any', fn ($query) => $query->whereIn('duty', ['both', $duty]))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function pointData(Request $request, FoundationEvent $event): array
    {
        return $request->validate([
            'region_id' => ['required', 'integer', Rule::exists('event_transport_regions', 'id')->where('event_id', $event->id)],
            'name' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function busData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plate' => ['nullable', 'string', 'max:40'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function stopData(Request $request, FoundationEvent $event): array
    {
        return $request->validate([
            'point_id' => ['required', 'integer', Rule::exists('event_transport_points', 'id')->where('event_id', $event->id)],
            'departs_at' => ['nullable', 'date'],
            'arrives_at' => ['nullable', 'date', 'after_or_equal:departs_at'],
        ]);
    }
}
