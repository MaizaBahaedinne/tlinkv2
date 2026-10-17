<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_notifications_page(): void
    {
        $user = User::factory()->create();

        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Bienvenue',
            'body' => 'Bienvenue sur T.Link',
        ]);

        $response = $this->actingAs($user)->get('/notifications');

        $response->assertOk();
        $response->assertSee('Notifications');
        $response->assertSee('Bienvenue');
    }

    public function test_user_can_mark_all_notifications_read_from_web_page(): void
    {
        $user = User::factory()->create();

        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'A',
            'body' => 'A',
        ]);

        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'B',
            'body' => 'B',
        ]);

        $response = $this->actingAs($user)->post('/notifications/read-all');

        $response->assertRedirect();

        $this->assertEquals(
            0,
            AppNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count()
        );
    }
}
