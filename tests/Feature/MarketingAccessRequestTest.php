<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Governorate;
use App\Models\MarketingAccessRequest;
use App\Models\MarketingAccount;
use App\Models\MarketingCharter;
use App\Models\OrgNode;
use App\Models\Profile;
use App\Models\User;
use App\Support\DigitalManagers;
use App\Support\MarketingEntities;
use App\Support\ModulePermissions;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Demandes d'accès aux comptes marketing : demande, validation par le digital manager, charte, mots de passe, portfolio Meta. */
class MarketingAccessRequestTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private OrgNode $club;

    private OrgNode $otherClub;

    private User $president;

    private User $otherPresident;

    private User $member;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernorateSeeder::class);
        app(OrgChartInstaller::class)->install();
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();
        $governorate = Governorate::query()->first();
        $this->club = app(OrgChartInstaller::class)->createClub($branch, 'ESPRIT', 'university_club', $governorate, true);
        $this->otherClub = app(OrgChartInstaller::class)->createClub($branch, 'ENIT', 'university_club', $governorate, true);

        $this->president = User::factory()->create();
        $this->placeOn($this->presidentNode($this->club), $this->president);
        $this->otherPresident = User::factory()->create();
        $this->placeOn($this->presidentNode($this->otherClub), $this->otherPresident);

        $this->member = User::factory()->create();
        $teamNode = OrgNode::query()->where('subtitle', $this->club->title)->where('kind', 'position')->where('role_key', '!=', 'president')->whereNotNull('role_key')->firstOrFail();
        $this->placeOn($teamNode, $this->member);
        $this->verify($this->member);

        $this->manager = User::factory()->create();
        // Poste habilité, limité à un périmètre : la validation ne dépend que de la matrice, pas d'une portée globale.
        $reviewerNode = OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'position', 'capacity' => 'multiple', 'category' => 'direction', 'title' => 'Poste habilité', 'scope' => 'university', 'sort_order' => 0]);
        ModulePermissions::apply($reviewerNode, ['marketing.review' => ['read', 'update']], false);
        $this->placeOn($reviewerNode, $this->manager);

        MarketingAccount::query()->create(['club_id' => $this->club->id, 'platform' => 'facebook', 'name' => 'Page ESPRIT', 'profile_url' => 'https://facebook.com/esprit', 'login' => 'esprit@example.org', 'password' => 'Secret-Fb-123!', 'two_factor' => 'none']);
        MarketingAccount::query()->create(['club_id' => $this->club->id, 'platform' => 'instagram', 'name' => 'Insta ESPRIT', 'profile_url' => 'https://instagram.com/esprit', 'two_factor' => 'none']);
    }

    private function presidentNode(OrgNode $club): OrgNode
    {
        return OrgNode::query()->where('subtitle', $club->title)->where('role_key', 'president')->firstOrFail();
    }

    private function verify(User $user): void
    {
        Profile::query()->firstOrCreate(['user_id' => $user->id])->forceFill(['identity_verified_at' => now(), 'phone' => '22111222', 'facebook_url' => 'https://facebook.com/'.$user->id])->save();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'entity' => 'club:'.$this->club->id, 'beneficiary_id' => $this->member->id, 'platforms' => ['facebook'],
            'facebook_profile_url' => 'https://facebook.com/membre', 'phone' => '22 111 222', 'reason' => 'Community manager',
        ];
    }

    private function submit(): MarketingAccessRequest
    {
        $this->actingAs($this->president)->post('/admin/marketing/demandes', $this->payload())->assertSessionHasNoErrors();

        return MarketingAccessRequest::query()->latest('id')->firstOrFail();
    }

    public function test_a_position_granted_the_review_module_reviews_requests_without_global_reach(): void
    {
        $this->assertContains($this->manager->id, DigitalManagers::userIds());
        $this->assertNotContains($this->president->id, DigitalManagers::userIds());
        $this->assertFalse(OrgNode::query()->where('system_key', 'digital-manager')->exists());

        $this->actingAs($this->manager)->get('/admin/marketing/validation')->assertOk();
        $this->actingAs($this->manager)->get('/admin/marketing/chartes')->assertOk();
    }

    public function test_a_club_position_requests_access_for_another_member(): void
    {
        $this->actingAs($this->president)->get('/admin/marketing/demandes/creer?entity=club:'.$this->club->id)->assertOk()->assertSee($this->member->name);

        $request = $this->submit();

        $this->assertSame('pending', $request->status);
        $this->assertSame($this->member->id, $request->beneficiary_id);
        $this->assertSame($this->president->id, $request->requester_id);
        $this->assertTrue(AppNotification::query()->where('user_id', $this->manager->id)->where('type', 'marketing_access')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'admin.marketing.request.created')->exists());

        $this->actingAs($this->president)->get('/admin/marketing/demandes')->assertOk()->assertSee('ESPRIT');
    }

    public function test_request_requires_a_verified_member_of_the_entity_with_an_existing_page(): void
    {
        $unverified = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $this->club->title)->where('kind', 'position')->where('role_key', '!=', 'president')->whereNotNull('role_key')->skip(1)->firstOrFail(), $unverified);

        $this->actingAs($this->president)->post('/admin/marketing/demandes', $this->payload(['beneficiary_id' => $unverified->id]))->assertSessionHasErrors('beneficiary_id');

        $stranger = User::factory()->create();
        $this->verify($stranger);
        $this->actingAs($this->president)->post('/admin/marketing/demandes', $this->payload(['beneficiary_id' => $stranger->id]))->assertSessionHasErrors('beneficiary_id');

        $this->actingAs($this->president)->post('/admin/marketing/demandes', $this->payload(['facebook_profile_url' => '', 'phone' => '']))->assertSessionHasErrors(['facebook_profile_url', 'phone']);

        MarketingAccount::query()->where('platform', 'instagram')->delete();
        $this->actingAs($this->president)->post('/admin/marketing/demandes', $this->payload(['platforms' => ['instagram']]))->assertSessionHasErrors('platforms');

        $this->actingAs($this->president)->post('/admin/marketing/demandes', $this->payload(['entity' => 'club:'.$this->otherClub->id]))->assertSessionHasErrors('entity');
        $this->assertSame(0, MarketingAccessRequest::query()->count());
    }

    public function test_only_positions_with_the_review_module_review_requests(): void
    {
        $request = $this->submit();

        $this->actingAs($this->president)->get('/admin/marketing/validation')->assertForbidden();
        $this->actingAs($this->president)->post("/admin/marketing/validation/{$request->id}/valider", ['platforms' => ['facebook']])->assertForbidden();
        $this->actingAs($this->president)->get('/admin/marketing/chartes')->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);

        // Une autre entité ne voit pas la demande.
        $this->actingAs($this->otherPresident)->get("/admin/marketing/demandes/{$request->id}")->assertForbidden();
        $this->actingAs($this->president)->get("/admin/marketing/demandes/{$request->id}")->assertOk();
    }

    public function test_full_flow_approval_charter_signature_passwords_and_meta_portfolio(): void
    {
        $request = $this->submit();

        $this->actingAs($this->manager)->get("/admin/marketing/validation/{$request->id}")->assertOk()->assertSee('Valider la demande');
        $this->actingAs($this->manager)->post("/admin/marketing/validation/{$request->id}/valider", ['platforms' => ['facebook']])->assertRedirect();

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertTrue(AppNotification::query()->where('user_id', $this->member->id)->where('title', 'like', 'Demande d’accès validée%')->exists());

        // Le lien de la notification est un chemin relatif (seuls ceux-là sont cliquables dans la liste).
        $link = AppNotification::query()->where('user_id', $this->member->id)->where('title', 'like', 'Demande d’accès validée%')->firstOrFail()->data['url'];
        $this->assertSame('/mes-acces-reseaux', $link);
        $this->actingAs($this->member)->get('/notifications')->assertOk()->assertSee('href="/mes-acces-reseaux"', false);

        // Pas de mot de passe avant la signature de la charte.
        $this->flushSession();
        $this->actingAs($this->member)->getJson("/mes-acces-reseaux/{$request->id}/acces")->assertForbidden();
        $this->actingAs($this->member)->get('/mes-acces-reseaux')->assertOk()->assertSee('Lire et signer la charte');
        $this->actingAs($this->member)->get("/mes-acces-reseaux/{$request->id}/charte")->assertOk()->assertSee('ESPRIT')->assertSee('Représentation');
        $this->actingAs($this->member)->post("/mes-acces-reseaux/{$request->id}/charte", ['signed_name' => 'Membre Test'])->assertSessionHasErrors('accept');

        // Seul le bénéficiaire signe.
        $this->actingAs($this->president)->post("/mes-acces-reseaux/{$request->id}/charte", ['accept' => 1, 'signed_name' => 'Président'])->assertForbidden();

        $this->actingAs($this->member)->post("/mes-acces-reseaux/{$request->id}/charte", ['accept' => 1, 'signed_name' => 'Membre Test'])->assertRedirect('/mes-acces-reseaux');
        $request->refresh();
        $this->assertSame('active', $request->status);
        $this->assertSame('Membre Test', $request->charter_signed_name);
        $this->assertStringContainsString('ESPRIT', (string) $request->charter_text);
        $this->assertNotNull($request->charter_signed_at);

        $response = $this->actingAs($this->member)->getJson("/mes-acces-reseaux/{$request->id}/acces")->assertOk()
            ->assertJsonPath('accounts.0.password', 'Secret-Fb-123!')->assertJsonPath('accounts.0.login', 'esprit@example.org');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertCount(1, $response->json('accounts'));
        $this->assertTrue(AuditLog::query()->where('action', 'marketing.access.revealed')->exists());

        // Un autre membre n'accède pas aux mots de passe.
        $this->actingAs($this->president)->getJson("/mes-acces-reseaux/{$request->id}/acces")->assertForbidden();

        // Portfolio Meta attribué par le digital manager.
        $this->actingAs($this->manager)->post("/admin/marketing/validation/{$request->id}/portfolio-meta", ['meta_portfolio' => 'ESPRIT — Portfolio 01'])->assertRedirect();
        $this->assertSame('ESPRIT — Portfolio 01', $request->fresh()->meta_portfolio);
        $this->actingAs($this->member)->getJson("/mes-acces-reseaux/{$request->id}/acces")->assertJsonPath('meta_portfolio', 'ESPRIT — Portfolio 01');

        // Retrait : plus de mot de passe.
        $this->actingAs($this->manager)->post("/admin/marketing/validation/{$request->id}/retirer", [])->assertRedirect();
        $this->assertSame('revoked', $request->fresh()->status);
        $this->actingAs($this->member)->getJson("/mes-acces-reseaux/{$request->id}/acces")->assertForbidden();
    }

    public function test_refusal_needs_a_reason_and_notifies(): void
    {
        $request = $this->submit();

        $this->actingAs($this->manager)->post("/admin/marketing/validation/{$request->id}/refuser", [])->assertSessionHasErrors('review_note');
        $this->actingAs($this->manager)->post("/admin/marketing/validation/{$request->id}/refuser", ['review_note' => 'Profil incomplet'])->assertRedirect('/admin/marketing/validation');

        $this->assertSame('refused', $request->fresh()->status);
        $this->assertTrue(AppNotification::query()->where('user_id', $this->president->id)->where('title', 'Demande d’accès refusée')->exists());

        $this->flushSession();
        $this->actingAs($this->member)->getJson("/mes-acces-reseaux/{$request->id}/acces")->assertForbidden();
        $this->actingAs($this->manager)->post("/admin/marketing/validation/{$request->id}/valider", ['platforms' => ['facebook']])->assertStatus(422);
    }

    public function test_a_duplicate_open_request_is_refused_and_the_requester_can_cancel(): void
    {
        $request = $this->submit();

        $this->actingAs($this->president)->post('/admin/marketing/demandes', $this->payload())->assertSessionHasErrors('platforms');

        $this->actingAs($this->president)->delete("/admin/marketing/demandes/{$request->id}")->assertRedirect('/admin/marketing/demandes');
        $this->assertSame('cancelled', $request->fresh()->status);
    }

    public function test_the_charter_is_editable_versioned_and_the_signed_text_is_frozen(): void
    {
        $request = $this->submit();
        $this->actingAs($this->manager)->post("/admin/marketing/validation/{$request->id}/valider", ['platforms' => ['facebook']]);
        $this->flushSession();
        $this->actingAs($this->member)->post("/mes-acces-reseaux/{$request->id}/charte", ['accept' => 1, 'signed_name' => 'Membre Test']);
        $signedText = $request->fresh()->charter_text;

        $this->actingAs($this->manager)->get('/admin/marketing/chartes/modifier?entity=default')->assertOk()->assertSee('Représentation');
        $this->actingAs($this->manager)->put('/admin/marketing/chartes', ['entity' => 'default', 'title' => 'Charte {entite}', 'body' => 'Article unique pour {entite}.'])->assertRedirect('/admin/marketing/chartes');
        $this->actingAs($this->manager)->put('/admin/marketing/chartes', ['entity' => 'default', 'title' => 'Charte {entite}', 'body' => 'Article modifié pour {entite}.']);

        $this->assertSame(2, MarketingCharter::query()->where('entity', 'default')->value('version'));
        $this->assertSame($signedText, $request->fresh()->charter_text);

        $this->actingAs($this->manager)->put('/admin/marketing/chartes', ['entity' => 'club:'.$this->club->id, 'title' => 'Charte ESPRIT', 'body' => 'Règle propre à {entite}.'])->assertRedirect();
        $charter = \App\Support\MarketingCharters::forEntity($this->club->id, null);
        $this->assertSame('Règle propre à ESPRIT.', $charter['body']);
        $this->assertSame('Article modifié pour ENIT.', \App\Support\MarketingCharters::forEntity($this->otherClub->id, null)['body']);

        $this->actingAs($this->manager)->post('/admin/marketing/chartes/supprimer', ['entity' => 'club:'.$this->club->id])->assertRedirect();
        $this->assertSame('Article modifié pour ESPRIT.', \App\Support\MarketingCharters::forEntity($this->club->id, null)['body']);
    }

    public function test_people_of_a_foundation_scope_are_listed_without_club_positions(): void
    {
        $people = MarketingEntities::people('scope:university');

        $this->assertNotContains($this->member->id, $people->pluck('id')->all());
    }

    public function test_a_member_without_rights_cannot_request(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/marketing/demandes')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/mes-acces-reseaux')->assertOk();
    }
}
