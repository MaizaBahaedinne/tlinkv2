<?php

namespace Tests\Feature;

use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_duplicate_event_registration_is_prevented(): void
    {
        $user = User::factory()->create();

        $event = FoundationEvent::query()->create([
            'title' => 'Forum Tunivisions',
            'starts_at' => now()->addDays(3),
            'status' => 'published',
            'capacity' => 20,
        ]);

        EventRegistration::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => 'confirmed',
            'registered_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/events/{$event->id}/register");

        $response->assertStatus(409);
    }

    public function test_event_capacity_moves_new_registration_to_waitlist(): void
    {
        $admin = User::factory()->create();
        $attendeeA = User::factory()->create();
        $attendeeB = User::factory()->create();

        $this->grantAccess($admin, 'global_admin');

        $create = $this->actingAs($admin)->postJson('/api/v1/events', [
            'title' => 'Capacity Test',
            'starts_at' => now()->addDay()->toIso8601String(),
            'capacity' => 1,
        ]);

        $create->assertCreated();
        $eventId = $create->json('data.id');

        $first = $this->actingAs($attendeeA)->postJson("/api/v1/events/{$eventId}/register");
        $first->assertCreated()->assertJsonPath('data.status', 'confirmed');

        $second = $this->actingAs($attendeeB)->postJson("/api/v1/events/{$eventId}/register");
        $second->assertCreated()->assertJsonPath('data.status', 'waitlisted');
    }
}
