<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LaunchGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class LaunchCountdownTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function closeUntil(string $when = '+10 days', bool $includeApp = true): void
    {
        LaunchGate::save([
            'enabled' => true,
            'include_app' => $includeApp,
            'launch_at' => now()->modify($when)->format('Y-m-d\TH:i'),
            'title' => 'Ouverture prochaine',
            'message' => '',
        ]);
    }

    private function globalAdmin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    public function test_site_is_open_by_default(): void
    {
        $this->get('/fr')->assertOk();
    }

    public function test_visitors_and_members_only_see_the_countdown_while_closed(): void
    {
        $this->closeUntil();

        $this->get('/fr')->assertStatus(503)->assertSee('Ouverture prochaine')->assertHeader('Retry-After');
        $this->get('/inscription')->assertStatus(503);
        $this->actingAs(User::factory()->create())->get('/feed')->assertStatus(503);
        $this->getJson('/api/v1/posts')->assertStatus(503)->assertJsonPath('message', 'Ouverture prochaine');

        $this->actingAs(User::factory()->create())->get('/fr/a-propos')->assertStatus(503);
    }

    public function test_login_page_stays_reachable_while_closed(): void
    {
        $this->closeUntil();

        $this->get('/connexion')->assertOk();
    }

    public function test_by_default_only_the_foundation_website_is_closed_and_tlink_stays_open(): void
    {
        $this->closeUntil('+10 days', false);

        $this->get('/fr')->assertStatus(503);
        $this->get('/fr/a-propos')->assertStatus(503);
        $this->get('/')->assertStatus(503);
        $this->get('/inscription')->assertOk();
        $this->get('/connexion')->assertOk();
        $this->actingAs(User::factory()->create())->get('/feed')->assertOk();
        $this->getJson('/api/v1/posts')->assertOk();
    }

    public function test_the_countdown_is_branded_for_the_foundation_and_offers_the_newsletter(): void
    {
        $this->closeUntil('+10 days', false);

        $this->get('/fr')->assertStatus(503)
            ->assertSee('Fondation Tunivisions')
            ->assertSee('Propulsé par Persomy.com')
            ->assertSee('id="newsletter"', false)
            ->assertSee(route('site.newsletter.store', ['locale' => 'fr']), false)
            ->assertDontSee('tlink-logo-white');
    }

    public function test_visitors_can_subscribe_to_the_newsletter_while_the_site_is_closed(): void
    {
        foreach ([true, false] as $includeApp) {
            $this->closeUntil('+10 days', $includeApp);

            $this->post('/fr/newsletter', ['email' => 'Visiteur@Example.test'])->assertRedirect(route('site.root'))->assertSessionHas('newsletter_sent');
        }

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'visiteur@example.test']);
        $this->get('/')->assertStatus(503)->assertSee('Votre adresse est enregistrée');
    }

    public function test_an_invalid_newsletter_address_comes_back_to_the_countdown_with_the_error(): void
    {
        $this->closeUntil();

        $this->from('/')->post('/fr/newsletter', ['email' => 'pas-un-email'])->assertRedirect('/')->assertSessionHasErrors('email');
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_position_join_links_stay_open_while_closed(): void
    {
        $this->closeUntil();
        $position = \App\Models\OrgNode::query()->create([
            'parent_id' => \App\Models\OrgNode::query()->where('system_key', 'university-office')->value('id'),
            'relation_type' => 'hierarchy',
            'kind' => 'position',
            'capacity' => 'single',
            'access_level' => 'none',
            'category' => 'national_office',
            'title' => 'Responsable communication',
            'sort_order' => 0,
        ]);

        $this->get('/rejoindre/'.$position->join_token)->assertOk()->assertSee('Responsable communication');

        $member = User::factory()->create();
        $this->actingAs($member)->post('/rejoindre/'.$position->join_token, ['message' => 'Motivé.'])
            ->assertRedirect(route('position.join', $position->join_token));
        $this->assertDatabaseHas('position_applications', ['node_id' => $position->id, 'user_id' => $member->id]);
    }

    public function test_position_invitation_links_stay_open_while_closed(): void
    {
        $this->closeUntil();
        $position = \App\Models\OrgNode::query()->create([
            'parent_id' => \App\Models\OrgNode::query()->where('system_key', 'university-office')->value('id'),
            'relation_type' => 'hierarchy',
            'kind' => 'position',
            'capacity' => 'single',
            'access_level' => 'none',
            'category' => 'national_office',
            'title' => 'Responsable communication',
            'sort_order' => 0,
        ]);
        $token = str_repeat('i', 40);
        \App\Models\PositionInvitation::query()->create([
            'node_id' => $position->id, 'first_name' => 'Amel', 'last_name' => 'Ben Salah', 'email' => 'amel@example.com',
            'token_hash' => \App\Models\PositionInvitation::hashToken($token), 'expires_at' => now()->addDays(14),
        ]);

        $this->get('/invitation/'.$token)->assertOk()->assertSee('Responsable communication')->assertSee('amel@example.com', false);
    }

    public function test_global_admin_still_sees_the_site_and_admin_area(): void
    {
        $this->closeUntil();

        $this->actingAs($this->globalAdmin());

        $this->get('/fr')->assertOk();
        $this->get('/admin/lancement')->assertOk();
    }

    public function test_admin_impersonating_a_member_keeps_access(): void
    {
        $this->closeUntil();
        $admin = $this->globalAdmin();
        $member = User::factory()->create();

        $this->actingAs($member)->withSession(['impersonator_id' => $admin->id])->get('/fr')->assertOk();
    }

    public function test_site_opens_by_itself_once_the_date_has_passed(): void
    {
        $this->closeUntil('-1 minute');

        $this->get('/fr')->assertOk();
    }

    public function test_only_global_admins_can_change_the_countdown(): void
    {
        $payload = ['enabled' => '1', 'launch_at' => now()->addDay()->format('Y-m-d\TH:i'), 'title' => 'Bientôt', 'message' => 'Patience'];

        $this->actingAs(User::factory()->create())->put('/admin/lancement', $payload)->assertForbidden();
        $this->assertFalse(LaunchGate::settings()['enabled']);

        $this->actingAs($this->globalAdmin())->put('/admin/lancement', $payload)->assertSessionHasNoErrors();
        $this->assertTrue(LaunchGate::settings()['enabled']);
        $this->assertTrue(LaunchGate::isClosed());
    }

    public function test_listed_members_bypass_the_countdown_while_others_stay_blocked(): void
    {
        $this->closeUntil();
        $allowed = User::factory()->create(['email' => 'testeur@exemple.tn']);
        $other = User::factory()->create();

        $payload = ['enabled' => '1', 'include_app' => '1', 'launch_at' => now()->addDay()->format('Y-m-d\TH:i'), 'allowed_emails' => "Testeur@Exemple.tn\n"];
        $this->actingAs($this->globalAdmin())->put('/admin/lancement', $payload)->assertSessionHasNoErrors();
        $this->assertSame([$allowed->id], LaunchGate::settings()['allowed_user_ids']);

        $this->actingAs($allowed)->get('/feed')->assertOk();
        $this->actingAs($other)->get('/feed')->assertStatus(503);
        $this->actingAs($allowed)->get('/admin/lancement')->assertForbidden();
    }

    public function test_unknown_emails_in_the_allow_list_are_rejected(): void
    {
        $this->actingAs($this->globalAdmin())
            ->put('/admin/lancement', ['enabled' => '1', 'launch_at' => now()->addDay()->format('Y-m-d\TH:i'), 'allowed_emails' => 'inconnu@exemple.tn'])
            ->assertSessionHasErrors('allowed_emails');
        $this->assertFalse(LaunchGate::settings()['enabled']);
    }

    public function test_the_allow_list_is_shown_on_the_admin_page(): void
    {
        User::factory()->create(['email' => 'visible@exemple.tn']);
        $admin = $this->globalAdmin();
        $this->actingAs($admin)->put('/admin/lancement', ['launch_at' => '', 'allowed_emails' => 'visible@exemple.tn']);

        $this->get('/admin/lancement')->assertOk()->assertSee('visible@exemple.tn');
    }

    public function test_enabling_requires_a_launch_date(): void
    {
        $this->actingAs($this->globalAdmin())->put('/admin/lancement', ['enabled' => '1'])->assertSessionHasErrors('launch_at');
        $this->assertFalse(LaunchGate::settings()['enabled']);
    }
}
