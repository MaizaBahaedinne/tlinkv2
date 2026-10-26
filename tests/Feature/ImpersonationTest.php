<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function globalAdmin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    public function test_global_admin_can_log_in_as_a_member_and_come_back(): void
    {
        $admin = $this->globalAdmin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post('/admin/utilisateurs/'.$member->id.'/se-connecter-en-tant-que')->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($member);
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'admin.impersonation.started', 'target_id' => $member->id]);

        $this->post('/impersonation/quitter')->assertRedirect(route('admin.users.show', $member));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'admin.impersonation.stopped']);
    }

    public function test_non_admin_cannot_impersonate(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($member)->post('/admin/utilisateurs/'.$other->id.'/se-connecter-en-tant-que')->assertForbidden();
        $this->assertAuthenticatedAs($member);
    }

    public function test_cannot_impersonate_self_another_global_admin_or_inactive_account(): void
    {
        $admin = $this->globalAdmin();
        $peer = $this->globalAdmin();
        $inactive = User::factory()->create(['is_active' => false]);

        foreach ([$admin, $peer, $inactive] as $target) {
            $this->actingAs($admin)->post('/admin/utilisateurs/'.$target->id.'/se-connecter-en-tant-que')->assertForbidden();
        }

        $this->assertAuthenticatedAs($admin);
    }

    public function test_stop_without_an_impersonation_is_refused(): void
    {
        $this->actingAs(User::factory()->create())->post('/impersonation/quitter')->assertForbidden();
    }
}
