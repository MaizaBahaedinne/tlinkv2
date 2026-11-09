<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Support\Messenger\Messenger;
use App\Support\Push\WebPush;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush as WebPushClient;
use Tests\TestCase;

/**
 * Notifications push dans le navigateur (Web Push / VAPID) : clés, abonnements, envoi et nettoyage des abonnements expirés.
 */
class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

    /** Client Web Push factice : enregistre les envois ; $expired liste les adresses déclarées expirées. */
    private function fakeClient(array $expired = []): object
    {
        $client = new class
        {
            public array $queued = [];

            public array $expired = [];

            public function queueNotification($subscription, ?string $payload = null): void
            {
                $this->queued[] = ['endpoint' => $subscription->getEndpoint(), 'payload' => json_decode((string) $payload, true)];
            }

            public function flush(): \Generator
            {
                foreach ($this->queued as $item) {
                    $expired = in_array($item['endpoint'], $this->expired, true);
                    $report = new MessageSentReport(new PsrRequest('POST', $item['endpoint']), $expired ? new \GuzzleHttp\Psr7\Response(410) : new \GuzzleHttp\Psr7\Response(201), ! $expired, $expired ? 'Gone' : 'OK');

                    yield $report;
                }
            }
        };
        $client->expired = $expired;

        $this->app->bind(WebPushClient::class, fn () => $client);

        return $client;
    }

    private function subscription(User $user, string $endpoint = self::ENDPOINT): WebPushSubscription
    {
        return WebPushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint_hash' => WebPushSubscription::hashEndpoint($endpoint),
            'endpoint' => $endpoint,
            'public_key' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
            'auth_token' => 'tBHItJI5svbpez7KI4CCXg',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    private function payload(string $endpoint = self::ENDPOINT): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg'], 'contentEncoding' => 'aes128gcm'];
    }

    public function test_vapid_keys_are_generated_once_and_the_private_key_is_encrypted(): void
    {
        $public = WebPush::publicKey();

        $this->assertNotEmpty($public);

        $setting = Setting::query()->where('group', WebPush::GROUP)->where('key', WebPush::KEY)->firstOrFail();
        $data = json_decode($setting->value, true);
        $this->assertSame($public, $data['public']);
        $this->assertStringNotContainsString('BEGIN', $data['private']);
        $this->assertNotEmpty(Crypt::decryptString($data['private']));

        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame($public, WebPush::publicKey());
        $this->assertSame(1, Setting::query()->where('group', WebPush::GROUP)->where('key', WebPush::KEY)->count());
    }

    public function test_only_browser_push_services_are_accepted_as_endpoints(): void
    {
        $this->assertTrue(WebPush::isAllowedEndpoint('https://fcm.googleapis.com/fcm/send/x'));
        $this->assertTrue(WebPush::isAllowedEndpoint('https://updates.push.services.mozilla.com/wpush/v2/x'));
        $this->assertTrue(WebPush::isAllowedEndpoint('https://web.push.apple.com/x'));
        $this->assertTrue(WebPush::isAllowedEndpoint('https://db5p.notify.windows.com/?token=x'));

        $this->assertFalse(WebPush::isAllowedEndpoint('http://fcm.googleapis.com/fcm/send/x'));
        $this->assertFalse(WebPush::isAllowedEndpoint('https://evil.example/fcm.googleapis.com'));
        $this->assertFalse(WebPush::isAllowedEndpoint('https://fcm.googleapis.com.evil.example/x'));
        $this->assertFalse(WebPush::isAllowedEndpoint('https://127.0.0.1/x'));
        $this->assertFalse(WebPush::isAllowedEndpoint('https://user@fcm.googleapis.com/x'));
        $this->assertFalse(WebPush::isAllowedEndpoint('https://fcm.googleapis.com:8443/x'));
    }

    public function test_a_browser_subscribes_moves_between_members_and_is_removed_by_its_owner(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();

        $this->postJson(route('devices.web.store'), $this->payload())->assertUnauthorized();

        $this->actingAs($alice)->postJson(route('devices.web.store'), $this->payload())->assertOk();
        $this->assertSame($alice->id, WebPushSubscription::query()->sole()->user_id);

        // Un autre membre se connecte sur le même navigateur : l'abonnement lui est rattaché.
        $this->actingAs($bob)->postJson(route('devices.web.store'), $this->payload())->assertOk();
        $this->assertSame($bob->id, WebPushSubscription::query()->sole()->user_id);

        $this->actingAs($alice)->deleteJson(route('devices.web.destroy'), ['endpoint' => self::ENDPOINT])->assertOk();
        $this->assertSame(1, WebPushSubscription::query()->count());

        $this->actingAs($bob)->deleteJson(route('devices.web.destroy'), ['endpoint' => self::ENDPOINT])->assertOk();
        $this->assertSame(0, WebPushSubscription::query()->count());
    }

    public function test_a_subscription_to_an_unknown_service_or_with_missing_keys_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('devices.web.store'), $this->payload('https://evil.example/push'))->assertStatus(422);
        $this->actingAs($user)->postJson(route('devices.web.store'), ['endpoint' => self::ENDPOINT])->assertStatus(422);
        $this->assertSame(0, WebPushSubscription::query()->count());
    }

    public function test_a_notification_is_pushed_to_the_members_browsers(): void
    {
        $client = $this->fakeClient();
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->subscription($alice);
        $this->subscription($bob, 'https://updates.push.services.mozilla.com/wpush/v2/bob');

        AppNotification::query()->create(['user_id' => $alice->id, 'type' => 'approval.decided', 'title' => 'Décision sur votre demande', 'body' => 'Votre demande a été approuvée.']);
        $this->app->terminate();

        $this->assertCount(1, $client->queued);
        $this->assertSame(self::ENDPOINT, $client->queued[0]['endpoint']);
        $this->assertSame('Décision sur votre demande', $client->queued[0]['payload']['title']);
        $this->assertStringEndsWith('/notifications', $client->queued[0]['payload']['url']);
    }

    public function test_a_message_is_pushed_to_the_other_participant_browsers_only(): void
    {
        $client = $this->fakeClient();
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->subscription($alice);
        $this->subscription($bob, 'https://updates.push.services.mozilla.com/wpush/v2/bob');
        $conversation = Messenger::direct($alice->fresh(), $bob->fresh());

        Messenger::send($conversation, $alice->fresh(), 'Salut Bob');
        $this->app->terminate();

        $this->assertCount(1, $client->queued);
        $this->assertSame('https://updates.push.services.mozilla.com/wpush/v2/bob', $client->queued[0]['endpoint']);
        $this->assertSame($alice->name, $client->queued[0]['payload']['title']);
        $this->assertSame('Salut Bob', $client->queued[0]['payload']['body']);
        $this->assertSame('/app-icons/messenger/icon-192.png', $client->queued[0]['payload']['icon']);
    }

    public function test_expired_subscriptions_are_removed(): void
    {
        $this->fakeClient([self::ENDPOINT]);
        $user = User::factory()->create();
        $this->subscription($user);

        $results = WebPush::sendToUsers([$user->id], 'Titre', 'Corps', url('/notifications'));

        $this->assertFalse($results[0]['ok']);
        $this->assertSame(0, WebPushSubscription::query()->count());
    }

    public function test_the_service_worker_is_served_and_the_notifications_page_offers_the_switch(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertStringContainsString("addEventListener('push'", (string) file_get_contents(public_path('sw.js')));
        $this->assertStringNotContainsString('focused', (string) file_get_contents(public_path('sw.js')));

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('data-web-push', false);
    }

    public function test_the_feed_requires_enabling_notifications_while_other_pages_only_invite(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('name="web-push"', false)->assertSee('data-required="1"', false);
        $this->get(route('notifications.index'))->assertOk()->assertSee('data-required="0"', false);
    }

    public function test_a_test_notification_is_sent_to_the_members_browsers(): void
    {
        $client = $this->fakeClient();
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->subscription($alice);
        $this->subscription($bob, 'https://updates.push.services.mozilla.com/wpush/v2/bob');

        $this->postJson(route('devices.web.test'))->assertUnauthorized();

        $this->actingAs($alice)->postJson(route('devices.web.test'))->assertOk()->assertJson(['ok' => true, 'sent' => 1]);

        $this->assertCount(1, $client->queued);
        $this->assertSame(self::ENDPOINT, $client->queued[0]['endpoint']);
    }

    public function test_the_test_command_reaches_browsers(): void
    {
        $client = $this->fakeClient();
        $user = User::factory()->create();
        $this->subscription($user);

        $this->artisan('push:test', ['email' => $user->email])->expectsOutputToContain('envoyé')->assertSuccessful();
        $this->assertCount(1, $client->queued);
    }
}
