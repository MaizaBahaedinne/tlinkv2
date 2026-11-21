<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\EventBus;
use App\Models\EventBusManager;
use App\Models\EventBusStop;
use App\Models\EventRegistration;
use App\Models\EventTeamMember;
use App\Models\EventTransportBooking;
use App\Models\EventTransportPoint;
use App\Models\EventTransportRegion;
use App\Models\FoundationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTransportTest extends TestCase
{
    use RefreshDatabase;

    private function event(): FoundationEvent
    {
        return FoundationEvent::query()->create([
            'title' => 'Forum Tunivisions',
            'location' => 'Palais des congrès',
            'starts_at' => now()->addDays(10),
            'status' => 'published',
        ]);
    }

    private function oc(FoundationEvent $event, string $role): User
    {
        $user = User::factory()->create();
        EventTeamMember::query()->create(['event_id' => $event->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function registered(FoundationEvent $event, string $status = 'confirmed'): User
    {
        $user = User::factory()->create();
        EventRegistration::query()->create([
            'event_id' => $event->id, 'user_id' => $user->id, 'status' => $status,
            'registered_at' => now(), 'amount_due' => 0, 'payment_status' => 'free',
        ]);

        return $user;
    }

    /** @return array{region: EventTransportRegion, point: EventTransportPoint, bus: EventBus, stop: EventBusStop} */
    private function setupBus(FoundationEvent $event, int $capacity = 2, ?string $regionName = 'Sfax'): array
    {
        $region = $event->transportRegions()->create(['name' => $regionName]);
        $point = $event->transportPoints()->create(['region_id' => $region->id, 'name' => 'Gare routière', 'city' => $regionName]);
        $bus = $event->buses()->create(['name' => 'Bus Sud', 'capacity' => $capacity]);
        $stop = $bus->stops()->create(['point_id' => $point->id, 'sort_order' => 1, 'departs_at' => now()->addDays(10)->setTime(6, 30), 'arrives_at' => now()->addDays(10)->setTime(9, 0)]);

        return compact('region', 'point', 'bus', 'stop');
    }

    public function test_transport_team_builds_regions_points_buses_circuit_and_managers(): void
    {
        $event = $this->event();
        $transport = $this->oc($event, 'transport');
        $volunteer = $this->oc($event, 'volunteer');
        $base = "/admin/evenements/{$event->id}/transport";

        $this->actingAs($volunteer)->get($base)->assertForbidden();
        $this->actingAs($volunteer)->post("$base/regions", ['name' => 'Sfax'])->assertForbidden();

        $this->actingAs($transport)->post("$base/regions", ['name' => 'Sfax'])->assertSessionHasNoErrors();
        $this->actingAs($transport)->post("$base/regions", ['name' => 'Sfax'])->assertSessionHasErrors('name');
        $region = EventTransportRegion::query()->firstOrFail();

        $this->actingAs($transport)->post("$base/points", [
            'region_id' => $region->id, 'name' => 'Gare routière', 'city' => 'Sfax', 'latitude' => '34.74', 'longitude' => '10.76',
        ])->assertSessionHasNoErrors();
        $this->actingAs($transport)->post("$base/points", [
            'region_id' => $region->id, 'name' => 'Sans longitude', 'city' => 'Sfax', 'latitude' => '34.74',
        ])->assertSessionHasErrors('longitude');
        $point = EventTransportPoint::query()->firstOrFail();
        $second = $event->transportPoints()->create(['region_id' => $region->id, 'name' => 'Péage', 'city' => 'Sfax']);

        // Un bus exige un point de départ choisi dans la liste de l'événement.
        $departs = now()->addDays(10)->setTime(6, 30)->format('Y-m-d H:i');
        $arrives = now()->addDays(10)->setTime(9, 0)->format('Y-m-d H:i');
        $this->actingAs($transport)->post("$base/bus", ['name' => 'Sans départ', 'capacity' => 50])->assertSessionHasErrors(['point_id', 'departs_at']);
        $foreign = $this->event()->transportRegions()->create(['name' => 'Ailleurs']);
        $foreignPoint = $foreign->event->transportPoints()->create(['region_id' => $foreign->id, 'name' => 'Autre', 'city' => 'X']);
        $this->actingAs($transport)->post("$base/bus", ['name' => 'Mauvais point', 'capacity' => 50, 'point_id' => $foreignPoint->id, 'departs_at' => $departs])->assertSessionHasErrors('point_id');
        $this->assertSame(0, EventBus::query()->count());

        $this->actingAs($transport)->post("$base/bus", ['name' => 'Bus Sud', 'capacity' => 50, 'point_id' => $point->id, 'departs_at' => $departs, 'arrives_at' => $arrives])->assertSessionHasNoErrors();
        $bus = EventBus::query()->firstOrFail();
        $this->assertSame([$point->id], $bus->stops()->pluck('point_id')->all());
        $this->assertSame(1, $bus->stops()->value('sort_order'));

        $this->actingAs($transport)->post("$base/bus/{$bus->id}/arrets", ['point_id' => $second->id, 'departs_at' => $departs])->assertSessionHasNoErrors();
        $this->actingAs($transport)->post("$base/bus/{$bus->id}/arrets", ['point_id' => $point->id])->assertSessionHasErrors('point_id');
        $this->assertSame([1, 2], $bus->stops()->pluck('sort_order')->all());

        // Le dernier arrêt (le départ) d'un bus ne peut pas être retiré.
        $this->actingAs($transport)->delete("$base/arrets/".$bus->stops()->where('point_id', $second->id)->value('id'))->assertSessionHas('status');
        $this->actingAs($transport)->delete("$base/arrets/".$bus->stops()->value('id'))->assertSessionHas('error');
        $this->assertSame(1, $bus->stops()->count());
        $this->actingAs($transport)->post("$base/bus/{$bus->id}/arrets", ['point_id' => $second->id, 'departs_at' => $departs])->assertSessionHasNoErrors();

        // Un second bus peut partir du même point.
        $this->actingAs($transport)->post("$base/bus", ['name' => 'Bus Sud 2', 'capacity' => 30, 'point_id' => $point->id, 'departs_at' => $departs])->assertSessionHasNoErrors();
        $second_bus = EventBus::query()->where('name', 'Bus Sud 2')->firstOrFail();
        $this->assertSame(2, EventBusStop::query()->where('point_id', $point->id)->count());
        $this->assertSame(1, $second_bus->stops()->count());

        $lead = User::factory()->create(['name' => 'Karim Responsable']);
        $this->actingAs($transport)->post("$base/bus/{$bus->id}/responsables", ['user_id' => $lead->id, 'duty' => 'both'])->assertSessionHasNoErrors();
        $this->assertTrue(EventBusManager::query()->where('bus_id', $bus->id)->where('user_id', $lead->id)->exists());
        $this->assertTrue(AppNotification::query()->where('user_id', $lead->id)->where('type', 'event.transport')->exists());

        $this->actingAs($transport)->get($base.'/membres?q=Karim')->assertOk()->assertJsonPath('members.0.id', $lead->id);
        $this->actingAs($transport)->get($base)->assertOk()->assertSee('Bus Sud')->assertSee('Gare routière')->assertSee('Karim Responsable')
            ->assertSee('id="bus-create"', false)->assertSee('id="stop-create-'.$bus->id.'"', false)->assertSee('id="manager-create-'.$bus->id.'"', false)
            ->assertSee('id="point-create-'.$region->id.'"', false)->assertSee('id="point-edit-'.$point->id.'"', false)
            ->assertSee('data-geo-picker', false)->assertSee('leaflet', false);

        // Un échec de validation rouvre la modale concernée.
        $this->actingAs($transport)->from($base)->post("$base/bus", ['_form' => 'bus-create', 'name' => '', 'capacity' => 10])->assertRedirect($base);
        $this->actingAs($transport)->get($base)->assertOk()->assertSee('data-modal-autoopen', false);

        // Un point utilisé dans un circuit ne peut pas être supprimé.
        $this->actingAs($transport)->delete("$base/points/{$point->id}")->assertSessionHas('error');
        $this->assertTrue(EventTransportPoint::query()->whereKey($point->id)->exists());
    }

    public function test_member_chooses_region_then_own_vehicle_or_bus_with_seat_limit(): void
    {
        $event = $this->event();
        ['region' => $region, 'bus' => $bus, 'stop' => $stop] = $this->setupBus($event, 1);
        $other = $event->transportRegions()->create(['name' => 'Sousse']);

        $first = $this->registered($event);
        $second = $this->registered($event);
        $stranger = User::factory()->create();

        // La page membre propose le transport aux inscrits.
        $this->actingAs($first)->get("/evenement/{$event->id}")->assertOk()->assertSee('Bus Sud')->assertSee('Sfax');

        // Véhicule personnel : pas de bus.
        $this->actingAs($first)->post("/evenement/{$event->id}/transport", ['region_id' => $region->id, 'mode' => 'own'])->assertSessionHas('status');
        $booking = EventTransportBooking::query()->where('user_id', $first->id)->firstOrFail();
        $this->assertSame('own', $booking->mode);
        $this->assertNull($booking->bus_id);

        // Bus : le point doit appartenir à la région choisie.
        $this->actingAs($first)->post("/evenement/{$event->id}/transport", ['region_id' => $other->id, 'mode' => 'bus', 'stop_id' => $stop->id])->assertSessionHas('error');
        $this->actingAs($first)->post("/evenement/{$event->id}/transport", ['region_id' => $region->id, 'mode' => 'bus'])->assertSessionHasErrors('stop_id');
        $this->actingAs($first)->post("/evenement/{$event->id}/transport", ['region_id' => $region->id, 'mode' => 'bus', 'stop_id' => $stop->id])->assertSessionHas('status');
        $this->assertSame($bus->id, $booking->refresh()->bus_id);
        $this->assertSame(1, EventTransportBooking::query()->count());

        // Capacité atteinte (1 place).
        $this->actingAs($second)->post("/evenement/{$event->id}/transport", ['region_id' => $region->id, 'mode' => 'bus', 'stop_id' => $stop->id])->assertSessionHas('error');
        $this->assertSame(0, $bus->refresh()->remaining());

        // Non inscrit : pas de transport.
        $this->actingAs($stranger)->post("/evenement/{$event->id}/transport", ['region_id' => $region->id, 'mode' => 'own'])->assertNotFound();

        // Annulation de l'inscription : la place est libérée.
        $this->actingAs($first)->delete("/evenement/{$event->id}/inscription")->assertSessionHas('status');
        $this->assertSame(1, $bus->refresh()->remaining());
        $this->actingAs($second)->post("/evenement/{$event->id}/transport", ['region_id' => $region->id, 'mode' => 'bus', 'stop_id' => $stop->id])->assertSessionHas('status');
    }

    public function test_waitlisted_registration_cannot_book_transport(): void
    {
        $event = $this->event();
        ['region' => $region] = $this->setupBus($event);
        $waiting = $this->registered($event, 'waitlisted');

        $this->actingAs($waiting)->post("/evenement/{$event->id}/transport", ['region_id' => $region->id, 'mode' => 'own'])->assertSessionHas('error');
        $this->assertSame(0, EventTransportBooking::query()->count());
    }

    public function test_bus_manager_marks_attendance_and_messages_passengers_within_their_duty(): void
    {
        $event = $this->event();
        ['region' => $region, 'bus' => $bus, 'stop' => $stop] = $this->setupBus($event, 5);
        $otherBus = $event->buses()->create(['name' => 'Bus Nord', 'capacity' => 5]);

        $lead = User::factory()->create();
        $presenceOnly = User::factory()->create();
        EventBusManager::query()->create(['bus_id' => $bus->id, 'user_id' => $lead->id, 'duty' => 'both']);
        EventBusManager::query()->create(['bus_id' => $bus->id, 'user_id' => $presenceOnly->id, 'duty' => 'attendance']);

        $passenger = $this->registered($event);
        $this->actingAs($passenger)->post("/evenement/{$event->id}/transport", ['region_id' => $region->id, 'mode' => 'bus', 'stop_id' => $stop->id])->assertSessionHas('status');
        $booking = EventTransportBooking::query()->firstOrFail();
        $base = "/admin/evenements/{$event->id}/transport";

        // Le responsable accède à ses bus uniquement, sans avoir de rôle OC.
        $this->actingAs($lead)->get($base)->assertOk()->assertSee('Bus Sud')->assertDontSee('Bus Nord');
        $this->actingAs($lead)->get("$base/bus/{$bus->id}")->assertOk()->assertSee($passenger->name);
        $this->actingAs($lead)->get("$base/bus/{$otherBus->id}")->assertForbidden();
        $this->actingAs($lead)->post("$base/regions", ['name' => 'Tunis'])->assertForbidden();
        $this->actingAs($lead)->get("/admin/mes-evenements")->assertOk()->assertSee('Forum Tunivisions');

        // Présence.
        $this->actingAs($lead)->patch("$base/reservations/{$booking->id}/presence", ['boarding' => 'present'])->assertSessionHas('status');
        $this->assertSame('present', $booking->refresh()->boarding);
        $this->assertSame($lead->id, $booking->marked_by);

        // Communication : réservée à la mission « communication ».
        $this->actingAs($presenceOnly)->post("$base/bus/{$bus->id}/message", ['message' => 'Bonjour'])->assertForbidden();
        $this->actingAs($lead)->post("$base/bus/{$bus->id}/message", ['message' => 'Départ avancé de 15 minutes.'])->assertSessionHas('status');
        $this->assertTrue(AppNotification::query()->where('user_id', $passenger->id)->where('type', 'event.transport')->where('body', 'Départ avancé de 15 minutes.')->exists());

        // Un autre membre ne peut pas pointer.
        $this->actingAs($passenger)->patch("$base/reservations/{$booking->id}/presence", ['boarding' => 'absent'])->assertForbidden();

        // Une fois pointé, le membre ne peut plus modifier son transport.
        $this->actingAs($passenger)->post("/evenement/{$event->id}/transport", ['region_id' => $region->id, 'mode' => 'own'])->assertSessionHas('error');
    }

    public function test_transport_role_is_in_the_oc_team_roles(): void
    {
        $event = $this->event();
        $director = $this->oc($event, 'director');
        $program = $this->oc($event, 'program');

        $this->actingAs($director)->get("/admin/evenements/{$event->id}/transport")->assertOk();
        $this->actingAs($program)->get("/admin/evenements/{$event->id}/transport")->assertForbidden();
    }
}
