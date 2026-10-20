<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AdminExportTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_non_admin_cannot_export_users_csv(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/api/v1/admin/exports/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_export_users_csv(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin');

        $response = $this->actingAs($admin)->get('/api/v1/admin/exports/users');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_can_export_event_registrations_csv(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $response = $this->actingAs($admin)->get('/api/v1/admin/exports/event-registrations');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
