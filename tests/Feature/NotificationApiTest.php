<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_notifications(): void
    {
        $user = User::factory()->create();

        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Bienvenue',
            'body' => 'Bienvenue sur T.Link',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonPath('data.0.title', 'Bienvenue');
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Action requise',
            'body' => 'Merci de verifier ton profil',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/notifications/'.$notification->id.'/read');

        $response->assertOk();
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_filter_unread_notifications(): void
    {
        $user = User::factory()->create();

        $read = AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Deja lu',
            'body' => 'Ancienne notification',
            'read_at' => now(),
        ]);

        $unread = AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Non lu',
            'body' => 'Notification active',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications?unread_only=1');

        $response->assertOk();
        $response->assertJsonMissing(['id' => $read->id]);
        $response->assertJsonFragment(['id' => $unread->id]);
    }
}
