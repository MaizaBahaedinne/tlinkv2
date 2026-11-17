<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Governorate;
use App\Models\OrgNode;
use App\Models\Sponsor;
use App\Models\SponsorContact;
use App\Models\SponsorLead;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Carnet des sponsors et partenaires et pistes de sponsoring des clubs. */
class SponsorTest extends TestCase
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

    private function sponsor(array $overrides = []): Sponsor
    {
        return Sponsor::query()->create($overrides + ['name' => 'Banque Zitouna', 'relation' => 'sponsor', 'status' => 'prospect', 'created_by' => $this->admin->id]);
    }

    public function test_a_sponsor_can_be_added_to_the_shared_directory_with_contacts(): void
    {
        $this->actingAs($this->president)->post('/admin/sponsors', ['name' => 'Telecom SA', 'relation' => 'both', 'status' => 'contacted', 'sector' => 'Télécoms', 'email' => 'contact@telecom.test'])
            ->assertRedirect();

        $sponsor = Sponsor::query()->where('name', 'Telecom SA')->firstOrFail();
        $this->assertSame($this->president->id, $sponsor->created_by);

        $this->actingAs($this->president)->post("/admin/sponsors/{$sponsor->id}/contacts", ['name' => 'Karim Trabelsi', 'job_title' => 'Responsable RSE', 'email' => 'karim@telecom.test', 'is_primary' => 1])->assertRedirect();
        $this->actingAs($this->president)->post("/admin/sponsors/{$sponsor->id}/contacts", ['name' => 'Nour Haddad', 'is_primary' => 1])->assertRedirect();

        $this->assertSame(2, $sponsor->contacts()->count());
        $this->assertSame(1, $sponsor->contacts()->where('is_primary', true)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'admin.sponsor.created')->exists());

        // Le carnet est partagé : un autre club le consulte et le recherche (y compris par interlocuteur).
        $this->actingAs($this->admin)->get('/admin/sponsors?q=Karim')->assertOk()->assertSee('Telecom SA');
    }

    public function test_sponsor_validation(): void
    {
        $this->actingAs($this->admin)->post('/admin/sponsors', ['name' => '', 'relation' => 'x', 'status' => 'y', 'website' => 'javascript:alert(1)'])
            ->assertSessionHasErrors(['name', 'relation', 'status', 'website']);
    }

    public function test_only_global_admin_or_creator_can_delete_a_sponsor(): void
    {
        $sponsor = $this->sponsor();

        $this->actingAs($this->president)->delete("/admin/sponsors/{$sponsor->id}")->assertForbidden();
        $this->assertTrue(Sponsor::query()->whereKey($sponsor->id)->exists());

        $this->actingAs($this->admin)->delete("/admin/sponsors/{$sponsor->id}")->assertRedirect('/admin/sponsors');
        $this->assertFalse(Sponsor::query()->whereKey($sponsor->id)->exists());
    }

    public function test_a_club_creates_a_lead_only_for_its_own_club(): void
    {
        $sponsor = $this->sponsor();
        $contact = SponsorContact::query()->create(['sponsor_id' => $sponsor->id, 'name' => 'Karim']);
        $lead = ['sponsor_id' => $sponsor->id, 'club_id' => $this->club->id, 'contact_id' => $contact->id, 'title' => 'Soirée de gala 2027', 'kind' => 'sponsoring', 'amount' => '5000', 'status' => 'idea', 'next_action_at' => now()->subDay()->toDateString()];

        $this->actingAs($this->president)->post('/admin/pistes-sponsoring', $lead)->assertRedirect();
        $this->assertSame(1, SponsorLead::query()->where('club_id', $this->club->id)->count());

        $this->actingAs($this->president)->post('/admin/pistes-sponsoring', ['club_id' => $this->otherClub->id] + $lead)->assertSessionHasErrors('club_id');
        $this->actingAs($this->president)->post('/admin/pistes-sponsoring', ['club_id' => null] + $lead)->assertSessionHasErrors('club_id');

        $other = SponsorContact::query()->create(['sponsor_id' => $this->sponsor(['name' => 'Autre'])->id, 'name' => 'Intrus']);
        $this->actingAs($this->president)->post('/admin/pistes-sponsoring', ['contact_id' => $other->id] + $lead)->assertSessionHasErrors('contact_id');
    }

    public function test_leads_are_scoped_and_closing_one_stamps_the_date(): void
    {
        $sponsor = $this->sponsor();
        $own = SponsorLead::query()->create(['sponsor_id' => $sponsor->id, 'club_id' => $this->club->id, 'title' => 'Piste ESPRIT', 'kind' => 'sponsoring', 'status' => 'proposal']);
        $foreign = SponsorLead::query()->create(['sponsor_id' => $sponsor->id, 'club_id' => $this->otherClub->id, 'title' => 'Piste ENIT', 'kind' => 'sponsoring', 'status' => 'contacted']);

        $this->actingAs($this->president)->get('/admin/pistes-sponsoring')->assertOk()->assertSee('Piste ESPRIT')->assertDontSee('Piste ENIT');

        $this->actingAs($this->president)->put("/admin/pistes-sponsoring/{$foreign->id}", ['sponsor_id' => $sponsor->id, 'club_id' => $this->otherClub->id, 'title' => 'x', 'kind' => 'sponsoring', 'status' => 'won'])->assertForbidden();
        $this->actingAs($this->president)->delete("/admin/pistes-sponsoring/{$foreign->id}")->assertForbidden();

        $this->actingAs($this->president)->put("/admin/pistes-sponsoring/{$own->id}", ['sponsor_id' => $sponsor->id, 'club_id' => $this->club->id, 'title' => 'Piste ESPRIT', 'kind' => 'sponsoring', 'status' => 'won', 'amount' => '8000'])->assertRedirect();

        $own->refresh();
        $this->assertSame('won', $own->status);
        $this->assertNotNull($own->closed_at);
        $this->assertTrue($own->isClosed());
    }

    public function test_a_sponsor_sheet_warns_when_other_clubs_already_work_with_it(): void
    {
        $sponsor = $this->sponsor();
        SponsorLead::query()->create(['sponsor_id' => $sponsor->id, 'club_id' => $this->otherClub->id, 'title' => 'Piste ENIT', 'kind' => 'sponsoring', 'status' => 'negotiation']);

        $page = $this->actingAs($this->president)->get("/admin/sponsors/{$sponsor->id}")->assertOk();

        $this->assertSame(['Club Tunivisions ENIT'], $page->viewData('otherClubs')->all());
        $page->assertDontSee('Piste ENIT');
    }

    public function test_a_member_without_rights_is_refused(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/admin/sponsors')->assertForbidden();
        $this->actingAs($member)->get('/admin/pistes-sponsoring')->assertForbidden();
    }

    public function test_module_matrix_is_enforced_on_the_sponsor_book(): void
    {
        $node = OrgNode::query()->where('subtitle', $this->club->title)->where('role_key', 'president')->firstOrFail();
        $matrix = (array) $node->module_permissions;
        $matrix['sponsoring.contacts'] = ['read'];
        $node->update(['module_permissions' => $matrix]);

        $this->actingAs($this->president)->get('/admin/sponsors')->assertOk();
        $this->actingAs($this->president)->post('/admin/sponsors', ['name' => 'Interdit', 'relation' => 'sponsor', 'status' => 'prospect'])->assertForbidden();
    }
}
