<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_non_admin_user_cannot_access_admin_overview(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/overview');

        $response->assertForbidden();
    }

    public function test_admin_user_can_access_admin_overview(): void
    {
        $user = User::factory()->create();
        $this->grantAccess($user, 'global_admin');

        $response = $this->actingAs($user)->getJson('/api/v1/admin/overview');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'is_global',
                'managed_scopes',
                'stats' => ['users', 'posts', 'memberships_pending', 'approvals_pending'],
            ],
        ]);
    }
}
