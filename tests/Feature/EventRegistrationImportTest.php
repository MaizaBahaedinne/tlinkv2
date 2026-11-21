<?php

namespace Tests\Feature;

use App\Models\EventPayment;
use App\Models\EventRegistration;
use App\Models\EventRegistrationImport;
use App\Models\FoundationEvent;
use App\Models\Mandate;
use App\Models\MandateParticipation;
use App\Models\User;
use App\Support\Events\RegistrationImportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class EventRegistrationImportTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function event(): FoundationEvent
    {
        return FoundationEvent::query()->create([
            'title' => 'Forum Tunivisions', 'location' => 'Tunis', 'starts_at' => now()->addDays(10), 'status' => 'published',
            // Inscriptions closes : les inscrits importés ne sont pas bloqués par la période d'ouverture.
            'registration_ends_at' => now()->subDay(),
        ]);
    }

    private function entry(FoundationEvent $event, array $extra = []): EventRegistrationImport
    {
        $release = $event->releases()->first() ?? $event->releases()->create(['name' => 'Première release', 'price' => 30, 'is_active' => true]);

        return EventRegistrationImport::query()->create($extra + [
            'event_id' => $event->id, 'release_id' => $release->id, 'first_name' => 'Farah', 'last_name' => 'Dhmaied',
            'phone' => '58 048 528', 'phone_key' => '58048528',
        ]);
    }

    private function formData(array $extra = []): array
    {
        return $extra + [
            'first_name' => 'Farah', 'last_name' => 'Dhmaied', 'gender' => 'female', 'birth_date' => '2002-04-12',
            'avatar' => UploadedFile::fake()->image('moi.jpg'),
            'email' => 'farah@example.com', 'password' => 'Secret-pass-12345', 'password_confirmation' => 'Secret-pass-12345',
            'method' => 'transfer', 'proof_camera' => UploadedFile::fake()->image('recu.jpg'),
        ];
    }

    public function test_the_parser_reads_spreadsheet_rows_and_reports_what_it_cannot_use(): void
    {
        $parsed = RegistrationImportParser::parse(implode("\n", [
            "Nom\tPrénom\tTéléphone",
            "dhmaied\tfarah\t58 048 528",
            "Melki \tYassmine \t21431495",
            "FIRAS\tTOUNSI\t+216 24 746 254",
            "Ben Dhieb\tMariem\t29 711 915",
            "Mejri nour 58 868 925",
            "doublon\tfarah\t58048528",
            "sans numéro",
            '',
        ]));

        $this->assertSame(['58048528', '21431495', '24746254', '29711915', '58868925'], array_column($parsed['rows'], 'phone_key'));
        $this->assertSame(['last_name' => 'Dhmaied', 'first_name' => 'Farah', 'phone' => '58 048 528', 'phone_key' => '58048528'], $parsed['rows'][0]);
        $this->assertSame('Ben Dhieb', $parsed['rows'][3]['last_name']);
        $this->assertSame('Nour', $parsed['rows'][4]['first_name']);
        $this->assertSame(1, $parsed['duplicates']);
        $this->assertSame(['sans numéro'], $parsed['invalid']);
    }

    public function test_an_admin_imports_a_list_onto_a_release_and_removes_a_pending_entry(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $event = $this->event();
        $release = $event->releases()->create(['name' => 'Première release', 'price' => 30, 'is_active' => true]);

        $this->actingAs($admin)->post("/admin/evenements/{$event->id}/inscrits-importes", [
            'list' => "Dhmaied\tFarah\t58 048 528\nGhabi\tMalek\t22 535 000", 'release_id' => $release->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(2, $event->registrationImports()->count());
        $this->assertSame($release->id, $event->registrationImports()->where('phone_key', '22535000')->value('release_id'));

        // Réimport : les numéros déjà présents sont ignorés.
        $this->post("/admin/evenements/{$event->id}/inscrits-importes", ['list' => "Dhmaied\tFarah\t58048528\nSfaxi\tSarra\t21723497"]);
        $this->assertSame(3, $event->registrationImports()->count());

        $this->get("/admin/evenements/{$event->id}")->assertOk()->assertSee('Fiches à compléter')->assertSee('Dhmaied')->assertSee('À compléter')->assertSee(route('event.complete', $event));

        $this->post("/admin/evenements/{$event->id}/inscrits-importes", ['list' => 'rien d’utile'])->assertSessionHasErrors('list');

        $entry = $event->registrationImports()->where('phone_key', '21723497')->firstOrFail();
        $this->delete("/admin/evenements/{$event->id}/inscrits-importes/{$entry->id}")->assertSessionHas('status');
        $this->assertDatabaseMissing('event_registration_imports', ['id' => $entry->id]);
    }

    public function test_a_participant_finds_their_sheet_with_their_phone_number_in_any_format(): void
    {
        $event = $this->event();
        $this->entry($event);

        $this->get("/inscription-evenement/{$event->id}")->assertOk()->assertSee('Retrouver ma fiche');

        $this->post("/inscription-evenement/{$event->id}/telephone", ['phone' => '99 999 999'])->assertSessionHasErrors('phone');
        $this->get("/inscription-evenement/{$event->id}")->assertDontSee('Envoyer ma fiche');

        $this->post("/inscription-evenement/{$event->id}/telephone", ['phone' => '+216 58-048-528'])->assertRedirect("/inscription-evenement/{$event->id}");
        $this->get("/inscription-evenement/{$event->id}")->assertOk()->assertSee('Envoyer ma fiche')->assertSee('Dhmaied')->assertSee('Première release')->assertSee('proof_camera', false);
    }

    public function test_a_draft_event_has_no_public_page(): void
    {
        $event = $this->event();
        $event->update(['status' => 'draft']);

        $this->get("/inscription-evenement/{$event->id}")->assertNotFound();
    }

    public function test_completing_the_sheet_creates_the_account_the_registration_the_payment_and_the_history(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $event = $this->event();
        $entry = $this->entry($event);
        $mandate = Mandate::query()->create(['label' => '2022-2023', 'start_year' => 2022, 'end_year' => 2023, 'bureau_name' => 'Bureau test', 'status' => Mandate::STATUS_CLOSED]);

        $this->post("/inscription-evenement/{$event->id}/telephone", ['phone' => '58048528']);

        $this->post("/inscription-evenement/{$event->id}", $this->formData([
            'scope' => 'university', 'joined_mandate_id' => $mandate->id, 'joined_scope' => 'high-school',
        ]))->assertSessionHasNoErrors()->assertRedirect("/inscription-evenement/{$event->id}");

        $user = User::query()->where('email', 'farah@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Farah', $user->first_name);
        $this->assertSame($mandate->id, $user->joined_mandate_id);
        $this->assertSame('58 048 528', $user->profile->phone);
        $this->assertSame('female', $user->profile->gender);
        $this->assertNotNull($user->profile->photo_path);
        Storage::disk('public')->assertExists($user->profile->photo_path);

        $registration = EventRegistration::query()->where('event_id', $event->id)->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('30.00', $registration->amount_due);
        $this->assertSame('pending', $registration->payment_status);

        $payment = EventPayment::query()->where('registration_id', $registration->id)->firstOrFail();
        $this->assertSame('transfer', $payment->method);
        $this->assertSame('30.00', $payment->amount);
        $this->assertSame($user->id, $payment->submitted_by);
        Storage::disk('local')->assertExists($payment->proof_path);

        $this->assertSame(1, MandateParticipation::query()->where('user_id', $user->id)->where('mandate_id', $mandate->id)->where('scope', 'high-school')->where('status', 'approved')->count());

        $entry->refresh();
        $this->assertTrue($entry->isCompleted());
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame($registration->id, $entry->registration_id);
        $this->assertSame(\App\Support\OrgScopes::label('university'), $entry->declared['scope'] ?? null);
        $this->assertSame('2022-2023', $entry->declared['joined']['mandate'] ?? null);

        $this->get("/inscription-evenement/{$event->id}")->assertOk()->assertSee('ta fiche est complétée');

        // Une fiche complétée ne se rouvre plus avec le numéro.
        $this->post('/logout');
        $this->post("/inscription-evenement/{$event->id}/telephone", ['phone' => '58048528'])->assertSessionHasErrors('phone');
    }

    public function test_the_payment_proof_is_required_unless_the_participant_postpones_it(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $event = $this->event();
        $this->entry($event);
        $this->post("/inscription-evenement/{$event->id}/telephone", ['phone' => '58048528']);

        $withoutProof = $this->formData();
        unset($withoutProof['proof_camera']);

        $this->post("/inscription-evenement/{$event->id}", $withoutProof)->assertSessionHasErrors('proof_file');
        $this->assertSame(0, User::query()->where('email', 'farah@example.com')->count());

        $later = $this->formData(['proof_later' => '1']);
        unset($later['proof_camera'], $later['method']);

        $this->post("/inscription-evenement/{$event->id}", $later)->assertSessionHasNoErrors();
        $registration = EventRegistration::query()->firstOrFail();
        $this->assertSame('unpaid', $registration->payment_status);
        $this->assertSame(0, EventPayment::query()->count());
    }

    public function test_the_transport_choice_is_part_of_the_sheet_when_the_event_organizes_a_bus(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $event = $this->event();
        $this->entry($event);
        $tunis = $event->transportRegions()->create(['name' => 'Tunis']);
        $sfax = $event->transportRegions()->create(['name' => 'Sfax']);
        $point = \App\Models\EventTransportPoint::query()->create(['event_id' => $event->id, 'region_id' => $tunis->id, 'name' => 'Place Barcelone', 'city' => 'Tunis']);
        $bus = \App\Models\EventBus::query()->create(['event_id' => $event->id, 'name' => 'Bus 1', 'capacity' => 1]);
        $stop = \App\Models\EventBusStop::query()->create(['bus_id' => $bus->id, 'point_id' => $point->id, 'departs_at' => now()->addDays(10)->setTime(6, 0)]);

        $this->post("/inscription-evenement/{$event->id}/telephone", ['phone' => '58048528']);
        $this->get("/inscription-evenement/{$event->id}")->assertOk()->assertSee('Région de départ')->assertSee('Sfax')->assertSee('Bus 1');

        // Région et mode sont obligatoires ; le bus aussi quand on choisit le bus.
        $this->post("/inscription-evenement/{$event->id}", $this->formData())->assertSessionHasErrors(['region_id', 'mode']);
        $this->post("/inscription-evenement/{$event->id}", $this->formData(['region_id' => $tunis->id, 'mode' => 'bus']))->assertSessionHasErrors('stop_id');
        // Un bus d'une autre région est refusé, sans rien créer.
        $this->post("/inscription-evenement/{$event->id}", $this->formData(['region_id' => $sfax->id, 'mode' => 'bus', 'stop_id' => $stop->id]))->assertSessionHasErrors('stop_id');
        $this->assertSame(0, User::query()->where('email', 'farah@example.com')->count());
        $this->assertSame(0, EventRegistration::query()->count());

        $this->post("/inscription-evenement/{$event->id}", $this->formData(['region_id' => $tunis->id, 'mode' => 'bus', 'stop_id' => $stop->id]))->assertSessionHasNoErrors();

        $registration = EventRegistration::query()->firstOrFail();
        $booking = $registration->transportBooking;
        $this->assertSame($tunis->id, $booking->region_id);
        $this->assertSame('bus', $booking->mode);
        $this->assertSame($stop->id, $booking->stop_id);

        $entry = EventRegistrationImport::query()->firstOrFail();
        $this->assertSame('Tunis', $entry->declared['transport']['region'] ?? null);
    }

    public function test_a_personal_vehicle_only_needs_the_departure_region(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $event = $this->event();
        $this->entry($event);
        $sfax = $event->transportRegions()->create(['name' => 'Sfax']);
        $this->post("/inscription-evenement/{$event->id}/telephone", ['phone' => '58048528']);

        $this->post("/inscription-evenement/{$event->id}", $this->formData(['region_id' => $sfax->id, 'mode' => 'own']))->assertSessionHasNoErrors();

        $booking = EventRegistration::query()->firstOrFail()->transportBooking;
        $this->assertSame('own', $booking->mode);
        $this->assertNull($booking->bus_id);
        $this->assertSame($sfax->id, $booking->region_id);
    }

    public function test_an_e_mail_that_already_has_an_account_is_refused_and_the_sheet_stays_open(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $event = $this->event();
        $entry = $this->entry($event);
        User::factory()->create(['email' => 'farah@example.com']);
        $this->post("/inscription-evenement/{$event->id}/telephone", ['phone' => '58048528']);

        $this->post("/inscription-evenement/{$event->id}", $this->formData())->assertSessionHasErrors('email');
        $this->assertFalse($entry->fresh()->isCompleted());
        $this->assertSame(0, EventRegistration::query()->count());
    }
}
