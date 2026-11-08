<?php

namespace Tests\Feature;

use App\Events\MessengerUpdated;
use App\Models\User;
use App\Support\Messenger\Messenger;
use App\Support\Messenger\Realtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Temps réel (Reverb) : facultatif, sans effet tant que la diffusion n'est pas configurée.
 */
class MessengerRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function enableReverb(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
            'broadcasting.connections.reverb.options.host' => 'ws.example.test',
            'broadcasting.connections.reverb.options.port' => 443,
            'broadcasting.connections.reverb.options.scheme' => 'https',
        ]);
    }

    /** @return array{0: User, 1: User, 2: User, 3: \App\Models\MessengerConversation} */
    private function conversation(): array
    {
        [$alice, $bob, $eve] = User::factory()->count(3)->create();

        return [$alice, $bob, $eve, Messenger::direct($alice->fresh(), $bob->fresh())];
    }

    public function test_nothing_is_broadcast_and_no_client_config_is_exposed_by_default(): void
    {
        Event::fake([MessengerUpdated::class]);
        [$alice, , , $conversation] = $this->conversation();

        $this->assertFalse(Realtime::enabled());
        $this->assertNull(Realtime::clientConfig());

        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Salut'])->assertCreated();
        $this->actingAs($alice)->get(route('messenger.show', $conversation))->assertOk()->assertDontSee('data-realtime', false);

        Event::assertNotDispatched(MessengerUpdated::class);
    }

    public function test_messages_deletions_and_typing_signal_the_participants_without_any_content(): void
    {
        $this->enableReverb();
        Event::fake([MessengerUpdated::class]);
        [$alice, $bob, , $conversation] = $this->conversation();

        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Secret'])->assertCreated();

        Event::assertDispatched(MessengerUpdated::class, function (MessengerUpdated $event) use ($conversation, $alice, $bob): bool {
            $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

            return $event->type === 'message'
                && $event->conversationId === $conversation->id
                && in_array('private-messenger.conversation.'.$conversation->id, $channels, true)
                && in_array('private-messenger.user.'.$bob->id, $channels, true)
                && in_array('private-messenger.user.'.$alice->id, $channels, true)
                && ! str_contains(json_encode($event->broadcastWith()), 'Secret');
        });

        $this->actingAs($alice)->postJson(route('messenger.typing', $conversation))->assertOk();
        Event::assertDispatched(MessengerUpdated::class, function (MessengerUpdated $event) use ($alice, $bob): bool {
            $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

            return $event->type === 'typing' && $event->by === $alice->id
                && in_array('private-messenger.user.'.$bob->id, $channels, true)
                && ! in_array('private-messenger.user.'.$alice->id, $channels, true);
        });

        $message = $conversation->messages()->firstOrFail();
        $this->actingAs($alice)->deleteJson(route('messenger.messages.destroy', $message))->assertOk();
        Event::assertDispatched(MessengerUpdated::class, fn (MessengerUpdated $event): bool => $event->type === 'deleted');
    }

    public function test_a_failing_broadcast_never_breaks_the_request(): void
    {
        // Reverb injoignable : l'envoi doit réussir quand même.
        $this->enableReverb();
        config(['broadcasting.connections.reverb.options.host' => '127.0.0.1', 'broadcasting.connections.reverb.options.port' => 1, 'broadcasting.connections.reverb.options.scheme' => 'http']);
        [$alice, , , $conversation] = $this->conversation();

        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Toujours livré'])->assertCreated();
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_client_configuration_is_exposed_to_the_page_when_enabled(): void
    {
        $this->enableReverb();
        [$alice, , , $conversation] = $this->conversation();

        $config = Realtime::clientConfig();
        $this->assertSame(['key' => 'test-key', 'host' => 'ws.example.test', 'port' => 443, 'tls' => true, 'auth' => '/broadcasting/auth'], $config);

        $this->actingAs($alice)->get(route('messenger.show', $conversation))->assertOk()->assertSee('data-realtime', false)->assertSee('ws.example.test')->assertDontSee('test-secret');
    }

    public function test_channels_are_reserved_to_participants_and_to_the_owner(): void
    {
        $this->enableReverb();
        // Les salons sont enregistrés au démarrage sur le pilote par défaut : on les recharge après avoir activé Reverb.
        require base_path('routes/channels.php');
        [$alice, $bob, $eve, $conversation] = $this->conversation();

        $auth = fn (User $user, string $channel) => $this->actingAs($user)->postJson('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);

        $auth($alice, 'private-messenger.conversation.'.$conversation->id)->assertOk()->assertJsonStructure(['auth']);
        $auth($bob, 'private-messenger.conversation.'.$conversation->id)->assertOk();
        $auth($eve, 'private-messenger.conversation.'.$conversation->id)->assertForbidden();
        $auth($alice, 'private-messenger.user.'.$alice->id)->assertOk();
        $auth($alice, 'private-messenger.user.'.$bob->id)->assertForbidden();
    }

    public function test_a_member_who_left_a_group_stops_receiving_signals(): void
    {
        $this->enableReverb();
        [$alice, $bob, $eve] = User::factory()->count(3)->create();
        $group = Messenger::createGroup($alice->fresh(), 'Équipe', [$bob->id, $eve->id]);
        Messenger::leave($group, $eve);

        Event::fake([MessengerUpdated::class]);
        Messenger::send($group, $alice, 'Bonjour');
        $this->app->terminate();

        Event::assertDispatched(MessengerUpdated::class, fn (MessengerUpdated $event): bool => in_array($bob->id, $event->userIds, true) && ! in_array($eve->id, $event->userIds, true));
    }
}
