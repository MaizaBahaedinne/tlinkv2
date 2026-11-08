<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\PushDevice;
use App\Models\User;
use App\Support\Messenger\Messenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sons de notification : fichiers, déclenchement dans l'en-tête, canaux Android des notifications push.
 */
class NotificationSoundsTest extends TestCase
{
    use RefreshDatabase;

    private string $credentialsFile;

    protected function tearDown(): void
    {
        if (isset($this->credentialsFile) && is_file($this->credentialsFile)) {
            unlink($this->credentialsFile);
        }

        parent::tearDown();
    }

    private function enableFcm(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->credentialsFile, json_encode([
            'project_id' => 'tlink-test',
            'client_email' => 'push@tlink-test.iam.gserviceaccount.com',
            'private_key' => $pem,
        ]));

        config(['services.fcm.credentials' => $this->credentialsFile]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-acces', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/tlink-test/messages/1']),
        ]);
    }

    private function sentToFcm(): \Illuminate\Support\Collection
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com'))->values();
    }

    private function device(User $user, string $token): void
    {
        PushDevice::query()->create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android', 'last_seen_at' => now()]);
    }

    public function test_sound_files_are_shipped_for_the_web_and_for_android(): void
    {
        foreach (['message.wav', 'notification.wav'] as $file) {
            $this->assertFileExists(public_path('sounds/'.$file));

            foreach (['link', 'store', 'academy', 'messenger'] as $app) {
                $this->assertFileExists(base_path("mobile/$app/android/app/src/main/res/raw/$file"));
            }
        }
    }

    public function test_header_embeds_the_sound_player_and_the_notification_counter(): void
    {
        $user = User::factory()->create();
        AppNotification::query()->create(['user_id' => $user->id, 'type' => 't', 'title' => 'Titre', 'body' => 'Corps']);

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('window.tlinkSound', false)
            ->assertSee('sounds', false)
            ->assertSee('id="nav-notifications"', false)
            ->assertSee('data-unread="1"', false)
            ->assertSee(route('notifications.unread'), false);
    }

    public function test_unread_notification_count_is_private_to_the_user(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        AppNotification::query()->create(['user_id' => $alice->id, 'type' => 't', 'title' => 'A', 'body' => 'a']);
        AppNotification::query()->create(['user_id' => $alice->id, 'type' => 't', 'title' => 'B', 'body' => 'b', 'read_at' => now()]);
        AppNotification::query()->create(['user_id' => $bob->id, 'type' => 't', 'title' => 'C', 'body' => 'c']);

        $this->actingAs($alice)->getJson(route('notifications.unread'))->assertOk()->assertExactJson(['unread' => 1]);

        auth()->logout();
        $this->getJson(route('notifications.unread'))->assertUnauthorized();
    }

    public function test_a_notification_is_pushed_on_the_tlink_channel_with_its_sound(): void
    {
        $this->enableFcm();
        $user = User::factory()->create();
        $this->device($user, str_repeat('t', 40));

        AppNotification::query()->create(['user_id' => $user->id, 'type' => 'approval.decided', 'title' => 'Décision sur votre demande', 'body' => 'Votre demande a été approuvée.']);

        // L'envoi se fait en fin de requête.
        $this->app->terminate();

        $sent = $this->sentToFcm();
        $this->assertCount(1, $sent);
        $message = $sent->first()['message'];
        $this->assertSame(str_repeat('t', 40), $message['token']);
        $this->assertSame('Décision sur votre demande', $message['notification']['title']);
        $this->assertSame('tlink', $message['android']['notification']['channel_id']);
        $this->assertSame('notification', $message['android']['notification']['sound']);
        $this->assertStringEndsWith('/notifications', $message['data']['url']);
    }

    public function test_a_message_is_pushed_on_the_messenger_channel_with_its_sound(): void
    {
        $this->enableFcm();
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = Messenger::direct($alice->fresh(), $bob->fresh());
        $this->device($bob, str_repeat('b', 40));

        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Salut'])->assertCreated();

        $message = $this->sentToFcm()->first()['message'];
        $this->assertSame('messenger', $message['android']['notification']['channel_id']);
        $this->assertSame('message', $message['android']['notification']['sound']);
    }

    public function test_nothing_is_pushed_when_firebase_is_not_configured(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->device($user, str_repeat('t', 40));

        AppNotification::query()->create(['user_id' => $user->id, 'type' => 't', 'title' => 'Titre', 'body' => 'Corps']);

        Http::assertNothingSent();
    }
}
