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

    private function closeUntil(string $when = '+10 days'): void
    {
        LaunchGate::save([
            'enabled' => true,
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
        $this->get('/feed')->assertStatus(503);
        $this->getJson('/api/v1/posts')->assertStatus(503)->assertJsonPath('message', 'Ouverture prochaine');

        $this->actingAs(User::factory()->create())->get('/fr/a-propos')->assertStatus(503);
    }

    public function test_login_page_stays_reachable_while_closed(): void
    {
        $this->closeUntil();

        $this->get('/connexion')->assertOk();
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

    public function test_enabling_requires_a_launch_date(): void
    {
        $this->actingAs($this->globalAdmin())->put('/admin/lancement', ['enabled' => '1'])->assertSessionHasErrors('launch_at');
        $this->assertFalse(LaunchGate::settings()['enabled']);
    }
}
