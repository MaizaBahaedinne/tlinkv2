<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Governorate;
use App\Models\MarketingAccount;
use App\Models\OrgNode;
use App\Models\User;
use Database\Seeders\GovernorateSeeder;
use App\Support\OrgChart\OrgChartInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Coffre des comptes marketing : secrets chiffrés, jamais affichés en liste, consultation tracée, portée par club. */
class MarketingAccountTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private OrgNode $club;

    private OrgNode $otherClub;

    private User $admin;

    private User $president;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernorateSeeder::class);
        app(OrgChartInstaller::class)->install();
        $branch = OrgNode::query()->where('system_key', 'university-clubs')->firstOrFail();
        $governorate = Governorate::query()->first();
        $this->club = app(OrgChartInstaller::class)->createClub($branch, 'ESPRIT', 'university_club', $governorate, true);
        $this->otherClub = app(OrgChartInstaller::class)->createClub($branch, 'ENIT', 'university_club', $governorate, true);

        $this->admin = User::factory()->create();
        $this->grantPlatformAdmin($this->admin);

        $this->president = User::factory()->create();
        $this->placeOn(OrgNode::query()->where('subtitle', $this->club->title)->where('role_key', 'president')->firstOrFail(), $this->president);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'entity' => 'club:'.$this->club->id, 'platform' => 'facebook', 'name' => 'Page ESPRIT', 'profile_url' => 'https://facebook.com/esprit',
            'login' => 'esprit@example.org', 'password' => 'Secret-Fb-123!', 'two_factor' => 'app', 'recovery_codes' => 'AAAA-BBBB',
        ];
    }

    public function test_password_is_optional_but_the_page_link_is_required(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload(['profile_url' => '']))->assertSessionHasErrors('profile_url');

        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload(['login' => '', 'password' => '']))->assertRedirect('/admin/marketing/comptes');

        $account = MarketingAccount::query()->firstOrFail();
        $this->assertFalse($account->hasPassword());
        $this->assertSame($this->club->id, $account->club_id);
    }

    public function test_password_is_stored_encrypted(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload())->assertRedirect('/admin/marketing/comptes');

        $account = MarketingAccount::query()->firstOrFail();
        $raw = DB::table('marketing_accounts')->where('id', $account->id)->first();

        $this->assertSame('Secret-Fb-123!', $account->password);
        $this->assertStringNotContainsString('Secret-Fb-123!', (string) $raw->password);
        $this->assertStringNotContainsString('AAAA-BBBB', (string) $raw->recovery_codes);
    }

    public function test_secrets_never_appear_in_list_or_edit_form(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload());

        $this->actingAs($this->admin)->get('/admin/marketing/comptes')->assertOk()->assertSee('Page ESPRIT')
            ->assertDontSee('Secret-Fb-123!')->assertDontSee('AAAA-BBBB');

        $account = MarketingAccount::query()->firstOrFail();
        $this->actingAs($this->admin)->get("/admin/marketing/comptes/{$account->id}/modifier")->assertOk()
            ->assertDontSee('Secret-Fb-123!')->assertDontSee('AAAA-BBBB');
    }

    public function test_revealing_a_secret_is_audited_and_not_cached(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload());
        $account = MarketingAccount::query()->firstOrFail();

        $response = $this->actingAs($this->admin)->getJson("/admin/marketing/comptes/{$account->id}/secret")->assertOk()
            ->assertJson(['login' => 'esprit@example.org', 'password' => 'Secret-Fb-123!']);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertTrue(AuditLog::query()->where('action', 'admin.marketing.account.revealed')->exists());

        $log = AuditLog::query()->where('action', 'admin.marketing.account.created')->firstOrFail();
        $this->assertStringNotContainsString('Secret-Fb-123!', json_encode($log->metadata));
    }

    public function test_blank_password_on_update_keeps_the_current_one(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload());
        $account = MarketingAccount::query()->firstOrFail();

        $this->actingAs($this->admin)->put("/admin/marketing/comptes/{$account->id}", $this->payload(['name' => 'Page renommée', 'password' => '', 'recovery_codes' => '']))
            ->assertRedirect('/admin/marketing/comptes');

        $account->refresh();
        $this->assertSame('Page renommée', $account->name);
        $this->assertSame('Secret-Fb-123!', $account->password);
        $this->assertSame('AAAA-BBBB', $account->recovery_codes);
    }

    public function test_compliance_lists_entities_missing_facebook_or_instagram(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload());

        $page = $this->actingAs($this->admin)->get('/admin/marketing/comptes')->assertOk();

        $page->assertSee('Fondation');
        $this->assertGreaterThan(0, $page->viewData('missing'));

        $this->actingAs($this->admin)->get('/admin/marketing/comptes?entity=club:'.$this->otherClub->id)->assertOk()->assertSee('ENIT');
        $this->actingAs($this->admin)->get('/admin/marketing/comptes?type=club&missing=1&entity=club:'.$this->club->id)->assertOk()->assertSee('ESPRIT');
    }

    public function test_pages_are_copied_to_the_club_and_the_foundation(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload(['entity' => 'scope:foundation', 'name' => 'Instagram Fondation', 'platform' => 'instagram', 'profile_url' => 'https://instagram.com/tunivisions']))->assertSessionHasNoErrors();

        $this->assertSame('https://facebook.com/esprit', $this->club->fresh()->facebook_url);
        $this->assertSame('https://instagram.com/tunivisions', (new \App\Support\SiteSettings())->raw()['instagram_url'] ?? null);
        $this->assertSame([['Facebook', 'fa-brands fa-facebook', 'https://facebook.com/esprit']], \App\Support\EntityPages::links($this->club->id, null));
    }

    public function test_a_link_saved_on_the_club_record_creates_its_page(): void
    {
        $this->otherClub->update(['instagram_url' => 'https://instagram.com/enit']);

        $account = MarketingAccount::query()->where('club_id', $this->otherClub->id)->firstOrFail();
        $this->assertSame('instagram', $account->platform);
        $this->assertSame('https://instagram.com/enit', $account->profile_url);

        $this->otherClub->update(['instagram_url' => null]);
        $this->assertNull($account->fresh()->profile_url);
    }

    public function test_a_club_manager_only_reaches_their_own_club(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload());
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload(['entity' => 'club:'.$this->otherClub->id, 'name' => 'Page ENIT', 'login' => 'enit@example.org']));
        $foreign = MarketingAccount::query()->where('club_id', $this->otherClub->id)->firstOrFail();
        $own = MarketingAccount::query()->where('club_id', $this->club->id)->firstOrFail();

        $this->flushSession();
        $this->actingAs($this->president)->get('/admin/marketing/comptes')->assertOk()->assertSee('Page ESPRIT')->assertDontSee('Page ENIT');
        $this->actingAs($this->president)->getJson("/admin/marketing/comptes/{$foreign->id}/secret")->assertForbidden();
        $this->actingAs($this->president)->get("/admin/marketing/comptes/{$foreign->id}/modifier")->assertForbidden();
        $this->actingAs($this->president)->delete("/admin/marketing/comptes/{$foreign->id}")->assertForbidden();
        $this->actingAs($this->president)->post('/admin/marketing/comptes', $this->payload(['entity' => 'club:'.$this->otherClub->id]))->assertSessionHasErrors('entity');
        $this->actingAs($this->president)->getJson("/admin/marketing/comptes/{$own->id}/secret")->assertOk();
    }

    public function test_a_member_without_rights_is_refused(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/marketing/comptes')->assertForbidden();
    }

    public function test_delete_is_audited(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload());
        $account = MarketingAccount::query()->firstOrFail();

        $this->actingAs($this->admin)->delete("/admin/marketing/comptes/{$account->id}")->assertRedirect('/admin/marketing/comptes');

        $this->assertSame(0, MarketingAccount::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'admin.marketing.account.deleted')->exists());
    }
}
