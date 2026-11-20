<?php

namespace Tests\Feature;

use App\Models\EventPayment;
use App\Models\EventRegistration;
use App\Models\EventRelease;
use App\Models\EventSession;
use App\Models\EventSpeaker;
use App\Models\EventSponsor;
use App\Models\EventSponsorLevel;
use App\Models\EventTeamMember;
use App\Models\FoundationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function event(array $extra = []): FoundationEvent
    {
        return FoundationEvent::query()->create($extra + [
            'title' => 'Forum Tunivisions',
            'location' => 'Palais des congrès',
            'starts_at' => now()->addDays(10),
            'status' => 'published',
        ]);
    }

    private function release(FoundationEvent $event, array $extra = []): EventRelease
    {
        return $event->releases()->create($extra + ['name' => 'Early bird', 'price' => 20, 'is_active' => true]);
    }

    private function oc(FoundationEvent $event, string $role): User
    {
        $user = User::factory()->create();
        EventTeamMember::query()->create(['event_id' => $event->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_admin_manages_releases_and_member_registers_with_a_price(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $event = $this->event();

        $this->actingAs($admin)->post("/admin/evenements/{$event->id}/billetterie", [
            'name' => 'Early bird', 'price' => '20', 'quantity' => '1', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/admin/evenements/{$event->id}/billetterie", [
            'name' => 'Standard', 'price' => '35', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $early = EventRelease::query()->where('name', 'Early bird')->firstOrFail();
        $standard = EventRelease::query()->where('name', 'Standard')->firstOrFail();
        $this->actingAs($admin)->get("/admin/evenements/{$event->id}/billetterie")->assertOk()->assertSee('Early bird')->assertSee('Standard');

        $member = User::factory()->create();
        $other = User::factory()->create();

        // Une release est obligatoire quand l'événement en propose.
        $this->actingAs($member)->post("/evenement/{$event->id}/inscription")->assertSessionHas('error');
        $this->assertSame(0, EventRegistration::query()->count());

        $this->actingAs($member)->post("/evenement/{$event->id}/inscription", ['release_id' => $early->id])->assertSessionHas('status');
        $registration = EventRegistration::query()->firstOrFail();
        $this->assertSame($early->id, $registration->release_id);
        $this->assertSame('20.00', $registration->amount_due);
        $this->assertSame('pending', $registration->status);
        $this->assertSame('unpaid', $registration->payment_status);

        // Quota de la release épuisé.
        $this->actingAs($other)->post("/evenement/{$event->id}/inscription", ['release_id' => $early->id])->assertSessionHas('error');
        $this->actingAs($other)->post("/evenement/{$event->id}/inscription", ['release_id' => $standard->id])->assertSessionHas('status');
        $this->assertSame('35.00', EventRegistration::query()->where('user_id', $other->id)->value('amount_due'));
    }

    public function test_api_registration_accepts_a_release_and_keeps_free_events_confirmed(): void
    {
        $event = $this->event();
        $free = User::factory()->create();
        $this->actingAs($free)->postJson("/api/v1/events/{$event->id}/register")->assertCreated()->assertJsonPath('data.status', 'confirmed');

        $paid = $this->event(['title' => 'Gala']);
        $release = $this->release($paid);
        $member = User::factory()->create();
        $this->actingAs($member)->postJson("/api/v1/events/{$paid->id}/register")->assertStatus(422);
        $this->actingAs($member)->postJson("/api/v1/events/{$paid->id}/register", ['release_id' => $release->id])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.payment_status', 'unpaid');
    }

    public function test_payment_with_proof_is_validated_by_a_different_person(): void
    {
        Storage::fake('local');
        $event = $this->event();
        $release = $this->release($event, ['price' => 30]);
        $member = User::factory()->create();
        $finance = $this->oc($event, 'finance');

        $this->actingAs($member)->post("/evenement/{$event->id}/inscription", ['release_id' => $release->id]);
        $registration = EventRegistration::query()->firstOrFail();

        // Preuve obligatoire.
        $this->actingAs($member)->post("/evenement/{$event->id}/paiement", ['method' => 'd17', 'amount' => '30'])->assertSessionHasErrors('proof');

        $this->actingAs($member)->post("/evenement/{$event->id}/paiement", [
            'method' => 'd17', 'amount' => '30', 'reference' => 'D17-123', 'proof' => UploadedFile::fake()->image('recu.jpg'),
        ])->assertSessionHas('status');

        $payment = EventPayment::query()->firstOrFail();
        $this->assertSame('pending', $payment->status);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->assertSame('pending', $registration->fresh()->payment_status);

        // Le payeur ne valide pas son propre paiement ; un membre sans rôle OC non plus.
        $this->actingAs($member)->post("/admin/evenements/{$event->id}/paiements/{$payment->id}/valider")->assertForbidden();
        $this->actingAs($this->oc($event, 'program'))->post("/admin/evenements/{$event->id}/paiements/{$payment->id}/valider")->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);

        // La preuve n'est pas publique : réservée à l'équipe.
        $this->actingAs($member)->get("/admin/evenements/{$event->id}/paiements/{$payment->id}/preuve")->assertForbidden();
        $this->actingAs($finance)->get("/admin/evenements/{$event->id}/paiements/{$payment->id}/preuve")->assertOk();

        $this->actingAs($finance)->post("/admin/evenements/{$event->id}/paiements/{$payment->id}/valider")->assertSessionHas('status');
        $payment->refresh();
        $this->assertSame('validated', $payment->status);
        $this->assertSame($finance->id, $payment->validated_by);

        $registration->refresh();
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('confirmed', $registration->status);

        // Déjà traité.
        $this->actingAs($finance)->post("/admin/evenements/{$event->id}/paiements/{$payment->id}/rejeter", ['reason' => 'x'])->assertSessionHas('error');
    }

    public function test_rejected_payment_keeps_the_registration_unpaid(): void
    {
        Storage::fake('local');
        $event = $this->event();
        $release = $this->release($event, ['price' => 30]);
        $member = User::factory()->create();
        $finance = $this->oc($event, 'finance');

        $this->actingAs($member)->post("/evenement/{$event->id}/inscription", ['release_id' => $release->id]);
        $this->actingAs($member)->post("/evenement/{$event->id}/paiement", [
            'method' => 'transfer', 'amount' => '30', 'proof' => UploadedFile::fake()->create('virement.pdf', 50, 'application/pdf'),
        ]);
        $payment = EventPayment::query()->firstOrFail();

        $this->actingAs($finance)->post("/admin/evenements/{$event->id}/paiements/{$payment->id}/rejeter")->assertSessionHasErrors('reason');
        $this->actingAs($finance)->post("/admin/evenements/{$event->id}/paiements/{$payment->id}/rejeter", ['reason' => 'Preuve illisible']);

        $this->assertSame('rejected', $payment->fresh()->status);
        $registration = EventRegistration::query()->firstOrFail();
        $this->assertSame('unpaid', $registration->payment_status);
        $this->assertSame('pending', $registration->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $member->id, 'type' => 'event.payment']);
    }

    public function test_team_records_a_cash_payment_and_partial_payments_add_up(): void
    {
        $event = $this->event();
        $release = $this->release($event, ['price' => 50]);
        $member = User::factory()->create();
        $director = $this->oc($event, 'director');
        $registrationDesk = $this->oc($event, 'registration');

        $this->actingAs($member)->post("/evenement/{$event->id}/inscription", ['release_id' => $release->id]);
        $registration = EventRegistration::query()->firstOrFail();

        // Le directeur encaisse 20 TND en cash : validé d'emblée.
        $this->actingAs($director)->post("/admin/evenements/{$event->id}/paiements", [
            'registration_id' => $registration->id, 'method' => 'cash', 'amount' => '20',
        ])->assertSessionHas('status');
        $this->assertSame('validated', EventPayment::query()->firstOrFail()->status);
        $this->assertSame('unpaid', $registration->fresh()->payment_status);

        // L'accueil enregistre mais ne peut pas valider : en attente.
        $this->actingAs($registrationDesk)->post("/admin/evenements/{$event->id}/paiements", [
            'registration_id' => $registration->id, 'method' => 'cash', 'amount' => '30',
        ]);
        $this->assertSame('pending', EventPayment::query()->latest('id')->firstOrFail()->status);

        // On ne peut pas dépasser le solde.
        $this->actingAs($director)->post("/admin/evenements/{$event->id}/paiements", [
            'registration_id' => $registration->id, 'method' => 'cash', 'amount' => '5',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($director)->post("/admin/evenements/{$event->id}/paiements/".EventPayment::query()->latest('id')->value('id').'/valider');
        $registration->refresh();
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('confirmed', $registration->status);
    }

    public function test_payment_methods_are_configured_per_event_and_limit_member_choices(): void
    {
        Storage::fake('local');
        $event = $this->event();
        $release = $this->release($event, ['price' => 10]);
        $finance = $this->oc($event, 'finance');

        $this->actingAs($finance)->put("/admin/evenements/{$event->id}/paiements/moyens", [
            'methods' => ['d17' => ['enabled' => '1', 'instructions' => 'D17 au 99 123 456'], 'transfer' => ['enabled' => '0', 'instructions' => '']],
        ])->assertSessionHas('status');

        $member = User::factory()->create();
        $this->actingAs($member)->post("/evenement/{$event->id}/inscription", ['release_id' => $release->id]);

        $this->actingAs($member)->get("/evenement/{$event->id}")->assertOk()->assertSee('D17 au 99 123 456');
        $this->actingAs($member)->post("/evenement/{$event->id}/paiement", [
            'method' => 'transfer', 'amount' => '10', 'proof' => UploadedFile::fake()->image('p.jpg'),
        ])->assertSessionHas('error');
        $this->assertSame(0, EventPayment::query()->count());
    }

    public function test_oc_roles_grant_precise_abilities(): void
    {
        $event = $this->event();
        $volunteer = $this->oc($event, 'volunteer');
        $program = $this->oc($event, 'program');
        $coordinator = $this->oc($event, 'coordinator');
        $stranger = User::factory()->create();

        // Un volontaire consulte l'aperçu, rien de plus.
        $this->actingAs($volunteer)->get("/admin/evenements/{$event->id}/apercu")->assertOk();
        $this->actingAs($volunteer)->get("/admin/evenements/{$event->id}/billetterie")->assertForbidden();
        $this->actingAs($volunteer)->get("/admin/evenements/{$event->id}/programme")->assertForbidden();

        // Programme : oui ; billetterie, paiements, équipe : non.
        $this->actingAs($program)->get("/admin/evenements/{$event->id}/programme")->assertOk();
        $this->actingAs($program)->get("/admin/evenements/{$event->id}/billetterie")->assertForbidden();
        $this->actingAs($program)->get("/admin/evenements/{$event->id}/equipe")->assertForbidden();

        // Le coordinateur gère l'équipe mais pas la validation des paiements.
        $this->actingAs($coordinator)->get("/admin/evenements/{$event->id}/equipe")->assertOk();
        $this->actingAs($coordinator)->get("/admin/evenements/{$event->id}/billetterie")->assertOk();

        // Un inconnu n'a aucun accès.
        $this->actingAs($stranger)->get("/admin/evenements/{$event->id}/apercu")->assertForbidden();
        $this->actingAs($stranger)->post("/admin/evenements/{$event->id}/billetterie", ['name' => 'x', 'price' => 1])->assertForbidden();

        // Mes événements OC.
        $this->actingAs($program)->get('/admin/mes-evenements')->assertOk()->assertSee('Forum Tunivisions');
    }

    public function test_team_is_managed_by_email_with_a_role(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $event = $this->event();
        $person = User::factory()->create(['email' => 'oc@example.test']);

        $this->actingAs($admin)->post("/admin/evenements/{$event->id}/equipe", ['email' => 'nobody@example.test', 'role' => 'finance'])->assertSessionHasErrors('user_id');
        $this->actingAs($admin)->post("/admin/evenements/{$event->id}/equipe", ['email' => 'oc@example.test', 'role' => 'finance'])->assertSessionHas('status');
        $member = EventTeamMember::query()->firstOrFail();
        $this->assertSame($person->id, $member->user_id);
        $this->assertDatabaseHas('notifications', ['user_id' => $person->id, 'type' => 'event.team']);

        $this->actingAs($admin)->put("/admin/evenements/{$event->id}/equipe/{$member->id}", ['role' => 'logistics']);
        $this->assertSame('logistics', $member->fresh()->role);

        $this->actingAs($admin)->get("/admin/evenements/{$event->id}/equipe")->assertOk()->assertSee('oc@example.test');
        $this->actingAs($admin)->delete("/admin/evenements/{$event->id}/equipe/{$member->id}");
        $this->assertSame(0, EventTeamMember::query()->count());
    }

    public function test_program_sessions_venues_speakers_and_step_registration(): void
    {
        $event = $this->event();
        $program = $this->oc($event, 'program');

        $this->actingAs($program)->post("/admin/evenements/{$event->id}/intervenants", [
            'name' => 'Dr Salma Ben Ali', 'kind' => 'guest_of_honor', 'origin' => 'external', 'organization' => 'UNESCO',
        ])->assertSessionHasNoErrors();
        $speaker = EventSpeaker::query()->firstOrFail();

        $this->actingAs($program)->post("/admin/evenements/{$event->id}/programme", [
            'type' => 'opening', 'title' => 'Cérémonie d’ouverture', 'starts_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'venue_mode' => 'event', 'room' => 'Grand amphi', 'speaker_ids' => [$speaker->id],
        ])->assertSessionHasNoErrors();
        $this->actingAs($program)->post("/admin/evenements/{$event->id}/programme", [
            'type' => 'workshop', 'title' => 'Atelier CV', 'starts_at' => now()->addDays(10)->addHours(3)->format('Y-m-d\TH:i'),
            'venue_mode' => 'external', 'venue_name' => 'Hôtel Majestic', 'venue_address' => 'Avenue Bourguiba',
            'requires_registration' => '1', 'capacity' => '1',
        ])->assertSessionHasNoErrors();

        // Un lieu externe doit être nommé.
        $this->actingAs($program)->post("/admin/evenements/{$event->id}/programme", [
            'type' => 'party', 'title' => 'Soirée', 'starts_at' => now()->addDays(10)->format('Y-m-d\TH:i'), 'venue_mode' => 'external',
        ])->assertSessionHasErrors('venue_name');

        $opening = EventSession::query()->where('type', 'opening')->firstOrFail();
        $workshop = EventSession::query()->where('type', 'workshop')->firstOrFail();
        $this->assertSame('Palais des congrès — Grand amphi', $opening->setRelation('event', $event)->venueLabel());
        $this->assertSame('Hôtel Majestic — Avenue Bourguiba', $workshop->venueLabel());
        $this->assertSame([$speaker->id], $opening->speakers()->pluck('event_speakers.id')->all());

        // Étape avec inscription : réservée aux inscrits, limitée par sa capacité.
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice)->post("/evenement/{$event->id}/etapes/{$workshop->id}")->assertNotFound();

        $this->actingAs($alice)->post("/evenement/{$event->id}/inscription");
        $this->actingAs($bob)->post("/evenement/{$event->id}/inscription");
        $this->actingAs($alice)->post("/evenement/{$event->id}/etapes/{$workshop->id}")->assertSessionHas('status');
        $this->actingAs($bob)->post("/evenement/{$event->id}/etapes/{$workshop->id}")->assertSessionHas('error');
        $this->actingAs($alice)->post("/evenement/{$event->id}/etapes/{$opening->id}")->assertSessionHas('error');

        $this->actingAs($alice)->delete("/evenement/{$event->id}/etapes/{$workshop->id}");
        $this->actingAs($bob)->post("/evenement/{$event->id}/etapes/{$workshop->id}")->assertSessionHas('status');

        $this->actingAs($alice)->get("/evenement/{$event->id}")->assertOk()
            ->assertSee('Cérémonie d’ouverture')->assertSee('Hôtel Majestic')->assertSee('Dr Salma Ben Ali')->assertSee('Inscription requise');
    }

    public function test_sponsors_are_grouped_by_custom_levels_on_the_public_page(): void
    {
        Storage::fake('public');
        $event = $this->event();
        $sponsoring = $this->oc($event, 'sponsoring');

        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors/niveaux", ['name' => 'Or', 'kind' => 'sponsor', 'rank' => 1])->assertSessionHasNoErrors();
        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors/niveaux", ['name' => 'Partenaire média', 'kind' => 'partner', 'rank' => 2]);
        $gold = EventSponsorLevel::query()->where('name', 'Or')->firstOrFail();
        $media = EventSponsorLevel::query()->where('name', 'Partenaire média')->firstOrFail();

        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors", [
            'level_id' => $gold->id, 'name' => 'Banque Zitouna', 'website_url' => 'https://example.test', 'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors", ['level_id' => $media->id, 'name' => 'Radio Express']);

        // Un niveau d'un autre événement est refusé.
        $foreign = $this->event(['title' => 'Autre'])->sponsorLevels()->create(['name' => 'Argent', 'kind' => 'sponsor', 'rank' => 1]);
        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors", ['level_id' => $foreign->id, 'name' => 'X'])->assertSessionHasErrors('level_id');

        $this->assertSame(2, EventSponsor::query()->count());
        Storage::disk('public')->assertExists(EventSponsor::query()->where('name', 'Banque Zitouna')->value('logo_path'));

        $this->get('/fr/evenements/'.$event->id)->assertOk()
            ->assertSeeInOrder(['Or', 'Banque Zitouna', 'Partenaire média', 'Radio Express']);

        // Un niveau non vide ne peut pas être supprimé.
        $this->actingAs($sponsoring)->delete("/admin/evenements/{$event->id}/sponsors/niveaux/{$gold->id}")->assertSessionHas('error');
        $this->assertNotNull($gold->fresh());
    }

    public function test_sponsor_can_be_picked_from_the_partner_database(): void
    {
        Storage::fake('public');
        $event = $this->event();
        $sponsoring = $this->oc($event, 'sponsoring');
        $level = $event->sponsorLevels()->create(['name' => 'Or', 'kind' => 'sponsor', 'rank' => 1]);

        $carnet = \App\Models\Sponsor::query()->create(['name' => 'Banque de Tunisie', 'relation' => 'sponsor', 'status' => 'active', 'website' => 'https://bt.example.test']);
        Storage::disk('public')->put('partners/logo.png', 'png-bytes');
        $site = \App\Models\Partner::query()->create(['name' => 'Ooredoo', 'logo_path' => 'partners/logo.png', 'website_url' => 'https://ooredoo.example.test']);

        $this->actingAs($sponsoring)->get("/admin/evenements/{$event->id}/sponsors")->assertOk()
            ->assertSee('Choisir dans ma base de partenaires')->assertSee('Banque de Tunisie')->assertSee('Ooredoo');

        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors", ['level_id' => $level->id, 'directory' => 's:'.$carnet->id])->assertSessionHasNoErrors();
        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors", ['level_id' => $level->id, 'directory' => 'p:'.$site->id])->assertSessionHasNoErrors();

        $fromCarnet = EventSponsor::query()->where('name', 'Banque de Tunisie')->firstOrFail();
        $this->assertSame($carnet->id, $fromCarnet->directory_sponsor_id);
        $this->assertSame('https://bt.example.test', $fromCarnet->website_url);

        $fromSite = EventSponsor::query()->where('name', 'Ooredoo')->firstOrFail();
        $this->assertSame($site->id, $fromSite->directory_partner_id);
        $this->assertNotSame('partners/logo.png', $fromSite->logo_path);
        Storage::disk('public')->assertExists($fromSite->logo_path);

        // Ni choix valide ni nom : refusé ; un nom saisi reste prioritaire.
        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors", ['level_id' => $level->id, 'directory' => 's:9999'])->assertSessionHasErrors('name');
        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors", ['level_id' => $level->id, 'name' => 'BT Assurances', 'directory' => 's:'.$carnet->id]);
        $this->assertTrue(EventSponsor::query()->where('name', 'BT Assurances')->exists());
        $this->actingAs($sponsoring)->post("/admin/evenements/{$event->id}/sponsors", ['level_id' => $level->id])->assertSessionHasErrors('name');
    }

    public function test_team_members_are_searched_in_the_database_with_a_free_text_post(): void
    {
        $event = $this->event();
        $director = $this->oc($event, 'director');
        $already = $this->oc($event, 'volunteer');
        $already->update(['name' => 'Salma Déjà']);
        $found = User::factory()->create(['name' => 'Salma Trabelsi', 'email' => 'salma@example.test']);
        User::factory()->create(['name' => 'Karim Autre', 'email' => 'karim@example.test']);

        $search = fn (string $q) => $this->actingAs($director)->getJson("/admin/evenements/{$event->id}/equipe/membres?q=".urlencode($q));

        $search('s')->assertOk()->assertJsonCount(0, 'members');
        $search('Salma')->assertOk()->assertJsonCount(1, 'members')->assertJsonPath('members.0.id', $found->id)->assertJsonPath('members.0.email', 'salma@example.test');
        $search('karim@')->assertJsonCount(1, 'members');

        // Réservé à ceux qui gèrent l'équipe.
        $this->actingAs($this->oc($event, 'program'))->getJson("/admin/evenements/{$event->id}/equipe/membres?q=Salma")->assertForbidden();

        $this->actingAs($director)->post("/admin/evenements/{$event->id}/equipe", [
            'user_id' => $found->id, 'role' => 'logistics', 'job_title' => 'Responsable transport & hébergement',
        ])->assertSessionHasNoErrors();

        $member = EventTeamMember::query()->where('user_id', $found->id)->firstOrFail();
        $this->assertSame('Responsable transport & hébergement', $member->job_title);
        $this->assertSame('logistics', $member->role);

        $this->actingAs($director)->put("/admin/evenements/{$event->id}/equipe/{$member->id}", ['role' => 'logistics', 'job_title' => 'Chef de projet']);
        $this->assertSame('Chef de projet', $member->fresh()->job_title);

        $this->actingAs($director)->post("/admin/evenements/{$event->id}/equipe", ['role' => 'finance'])->assertSessionHasErrors('user_id');
        $this->actingAs($director)->get("/admin/evenements/{$event->id}/equipe")->assertOk()->assertSee('Rechercher par nom ou e-mail')->assertSee('Chef de projet');
    }

    public function test_cancelling_a_registration_promotes_the_waitlist(): void
    {
        $event = $this->event(['capacity' => 1]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->post("/evenement/{$event->id}/inscription");
        $this->actingAs($second)->post("/evenement/{$event->id}/inscription");
        $this->assertSame('waitlisted', EventRegistration::query()->where('user_id', $second->id)->value('status'));

        $this->actingAs($first)->delete("/evenement/{$event->id}/inscription")->assertSessionHas('status');

        $this->assertSame('cancelled', EventRegistration::query()->where('user_id', $first->id)->value('status'));
        $this->assertSame('confirmed', EventRegistration::query()->where('user_id', $second->id)->value('status'));

        // Réinscription possible après annulation.
        $this->actingAs($first)->post("/evenement/{$event->id}/inscription");
        $this->assertSame('waitlisted', EventRegistration::query()->where('user_id', $first->id)->value('status'));
        $this->assertSame(2, EventRegistration::query()->count());
    }

    public function test_closed_registrations_and_unpublished_events_are_refused(): void
    {
        $closed = $this->event(['registration_ends_at' => now()->subDay()]);
        $draft = $this->event(['title' => 'Brouillon', 'status' => 'draft']);
        $member = User::factory()->create();

        $this->actingAs($member)->post("/evenement/{$closed->id}/inscription")->assertSessionHas('error');
        $this->actingAs($member)->post("/evenement/{$draft->id}/inscription")->assertNotFound();
        $this->actingAs($member)->get("/evenement/{$draft->id}")->assertNotFound();
        $this->assertSame(0, EventRegistration::query()->count());
    }

    public function test_global_admin_manages_any_event_without_being_in_the_team(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $event = $this->event();

        foreach (['apercu', 'inscriptions', 'billetterie', 'programme', 'intervenants', 'sponsors', 'paiements', 'equipe'] as $page) {
            $this->actingAs($admin)->get("/admin/evenements/{$event->id}/{$page}")->assertOk();
        }

        $this->actingAs($admin)->get("/admin/evenements/{$event->id}")->assertOk()->assertSee('Gérer');
    }
}
