<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class PostAuthorizationTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_member_without_permission_cannot_publish_public_post_from_web(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/posts', [
                'content' => 'Post public non autorise',
                'visibility' => 'public',
            ])
            ->assertSessionHasErrors('visibility');

        $this->assertDatabaseMissing('posts', [
            'user_id' => $user->id,
            'content' => 'Post public non autorise',
            'visibility' => 'public',
        ]);
    }

    public function test_member_without_permission_can_publish_non_public_post_from_web(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/posts', [
                'content' => 'Post communaute autorise',
                'visibility' => 'authenticated',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Post communaute autorise',
            'visibility' => 'authenticated',
        ]);
    }

    public function test_user_with_access_level_can_publish_public_post_from_web(): void
    {
        $user = User::factory()->create();
        $this->grantAccess($user, 'manager', 'university');

        $this->actingAs($user)
            ->post('/posts', [
                'content' => 'Post public autorise',
                'visibility' => 'public',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Post public autorise',
            'visibility' => 'public',
        ]);
    }

    public function test_member_without_permission_cannot_publish_public_post_via_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/posts', [
                'content' => 'Post public API non autorise',
                'visibility' => 'public',
                'type' => 'text',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('visibility');

        $this->assertDatabaseMissing('posts', [
            'user_id' => $user->id,
            'content' => 'Post public API non autorise',
            'visibility' => 'public',
        ]);
    }

    public function test_user_with_access_level_can_publish_public_post_via_api(): void
    {
        $user = User::factory()->create();
        $this->grantAccess($user, 'manager', 'university');

        $this->actingAs($user)
            ->postJson('/api/v1/posts', [
                'content' => 'Post public API autorise',
                'visibility' => 'public',
                'type' => 'text',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Post public API autorise',
            'visibility' => 'public',
        ]);
    }

    public function test_feed_payload_hides_public_visibility_for_non_authorized_member(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/feed')
            ->assertOk()
            ->assertSee('"allowed_visibilities":["authenticated","entity","department","club","restricted"]', false)
            ->assertDontSee('"can_publish_public":true', false);
    }

    public function test_feed_payload_allows_public_visibility_for_authorized_member(): void
    {
        $user = User::factory()->create();
        $this->grantAccess($user, 'manager', 'university');

        $this->actingAs($user)
            ->get('/feed')
            ->assertOk()
            ->assertSee('"can_publish_public":true', false)
            ->assertSee('"allowed_visibilities":["public","authenticated","entity","department","club","restricted"]', false);
    }
}
