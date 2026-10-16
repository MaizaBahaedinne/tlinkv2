<?php

namespace Tests\Feature;

use App\Models\ModerationReport;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_report_post(): void
    {
        $reporter = User::factory()->create();
        $author = User::factory()->create();

        $post = Post::query()->create([
            'user_id' => $author->id,
            'type' => 'text',
            'visibility' => 'authenticated',
            'content' => 'Post a signaler',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($reporter)->postJson('/api/v1/moderation/reports', [
            'post_id' => $post->id,
            'reason' => 'Contenu inapproprie',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('moderation_reports', [
            'reported_by' => $reporter->id,
            'reportable_id' => $post->id,
            'status' => 'pending',
        ]);
    }

    public function test_only_moderator_can_review_report(): void
    {
        $moderator = User::factory()->create();
        $reporter = User::factory()->create();

        $permission = Permission::query()->create([
            'name' => 'Moderate Post',
            'slug' => 'post.moderate',
        ]);

        $role = Role::query()->create([
            'name' => 'Club Officer',
            'slug' => 'club-officer',
            'scope_type' => 'club',
        ]);

        $role->permissions()->attach($permission->id);

        RoleAssignment::query()->create([
            'user_id' => $moderator->id,
            'role_id' => $role->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
        ]);

        $report = ModerationReport::query()->create([
            'reported_by' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => 999,
            'reason' => 'Test',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($moderator)->postJson("/api/v1/admin/moderation-reports/{$report->id}/review", [
            'status' => 'reviewed',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('moderation_reports', [
            'id' => $report->id,
            'status' => 'reviewed',
            'reviewed_by' => $moderator->id,
        ]);
    }
}
