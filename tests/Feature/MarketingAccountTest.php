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
            'club_id' => $this->club->id, 'platform' => 'facebook', 'name' => 'Page ESPRIT', 'login' => 'esprit@example.org',
            'password' => 'Secret-Fb-123!', 'two_factor' => 'app', 'recovery_codes' => 'AAAA-BBBB',
        ];
    }

    public function test_password_is_required_and_stored_encrypted(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload(['password' => '']))->assertSessionHasErrors('password');

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

    public function test_compliance_lists_clubs_missing_facebook_or_instagram(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload());

        $page = $this->actingAs($this->admin)->get('/admin/marketing/comptes')->assertOk();

        $page->assertSee('ENIT')->assertSee('ESPRIT');
        $this->assertGreaterThan(0, $page->viewData('missing'));
    }

    public function test_a_club_manager_only_reaches_their_own_club(): void
    {
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload());
        $this->actingAs($this->admin)->post('/admin/marketing/comptes', $this->payload(['club_id' => $this->otherClub->id, 'name' => 'Page ENIT', 'login' => 'enit@example.org']));
        $foreign = MarketingAccount::query()->where('club_id', $this->otherClub->id)->firstOrFail();
        $own = MarketingAccount::query()->where('club_id', $this->club->id)->firstOrFail();

        $this->flushSession();
        $this->actingAs($this->president)->get('/admin/marketing/comptes')->assertOk()->assertSee('Page ESPRIT')->assertDontSee('Page ENIT');
        $this->actingAs($this->president)->getJson("/admin/marketing/comptes/{$foreign->id}/secret")->assertForbidden();
        $this->actingAs($this->president)->get("/admin/marketing/comptes/{$foreign->id}/modifier")->assertForbidden();
        $this->actingAs($this->president)->delete("/admin/marketing/comptes/{$foreign->id}")->assertForbidden();
        $this->actingAs($this->president)->post('/admin/marketing/comptes', $this->payload(['club_id' => $this->otherClub->id]))->assertSessionHasErrors('club_id');
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
