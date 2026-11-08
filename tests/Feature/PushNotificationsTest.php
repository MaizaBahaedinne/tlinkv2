<?php

namespace Tests\Feature;

use App\Models\PushDevice;
use App\Models\User;
use App\Support\Messenger\Messenger;
use App\Support\Push\Fcm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Notifications push de la messagerie (Firebase Cloud Messaging) : appareils, envoi aux autres participants, nettoyage des jetons périmés.
 */
class PushNotificationsTest extends TestCase
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
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        config(['services.fcm.credentials' => $this->credentialsFile]);
    }

    private function device(User $user, string $token): PushDevice
    {
        return PushDevice::query()->create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android', 'last_seen_at' => now()]);
    }

    private function sentToFcm(): \Illuminate\Support\Collection
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])->filter(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com'))->values();
    }

    public function test_the_app_can_report_its_push_status_for_diagnosis(): void
    {
        $user = User::factory()->create();
        \Illuminate\Support\Facades\Log::spy();

        $this->postJson(route('devices.diagnostic'), ['step' => 'demarrage'])->assertUnauthorized();

        $this->actingAs($user)->postJson(route('devices.diagnostic'), ['step' => 'plugin-indisponible', 'detail' => 'not implemented', 'platform' => 'android'])
            ->assertOk();
        $this->postJson(route('devices.diagnostic'), [])->assertUnprocessable();

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'Push appareil : plugin-indisponible' && $context['user'] === $user->id)->once();
    }

    public function test_devices_are_registered_moved_between_members_and_removed_by_their_owner(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $token = str_repeat('a', 40);

        $this->postJson('/appareils', ['token' => $token, 'platform' => 'android'])->assertUnauthorized();
        $this->actingAs($alice)->postJson('/appareils', ['token' => 'court', 'platform' => 'android'])->assertStatus(422);
        $this->actingAs($alice)->postJson('/appareils', ['token' => $token, 'platform' => 'windows'])->assertStatus(422);

        $this->actingAs($alice)->postJson('/appareils', ['token' => $token, 'platform' => 'android'])->assertOk();
        $this->assertSame($alice->id, PushDevice::query()->where('token', $token)->value('user_id'));

        // Un autre membre se connecte sur le même appareil : le jeton lui est rattaché, sans doublon.
        $this->actingAs($bob)->postJson('/appareils', ['token' => $token, 'platform' => 'android'])->assertOk();
        $this->assertSame(1, PushDevice::query()->count());
        $this->assertSame($bob->id, PushDevice::query()->value('user_id'));

        $this->actingAs($alice)->deleteJson('/appareils', ['token' => $token])->assertOk();
        $this->assertSame(1, PushDevice::query()->count());
        $this->actingAs($bob)->deleteJson('/appareils', ['token' => $token])->assertOk();
        $this->assertSame(0, PushDevice::query()->count());
    }

    public function test_nothing_is_sent_without_firebase_credentials(): void
    {
        Http::fake();
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->device($bob, str_repeat('b', 40));
        $conversation = Messenger::direct($alice->fresh(), $bob->fresh());

        $this->assertFalse(Fcm::enabled());
        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Salut'])->assertCreated();

        Http::assertNothingSent();
    }

    public function test_a_direct_message_notifies_the_other_member_only(): void
    {
        $this->enableFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-acces', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/tlink-test/messages/1']),
        ]);

        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = Messenger::direct($alice->fresh(), $bob->fresh());
        $this->device($alice, str_repeat('a', 40));
        $this->device($bob, str_repeat('b', 40));

        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Bonjour Bob'])->assertCreated();

        $sent = $this->sentToFcm();
        $this->assertCount(1, $sent);
        $request = $sent->first();
        $this->assertSame('https://fcm.googleapis.com/v1/projects/tlink-test/messages:send', $request->url());
        $this->assertSame('Bearer jeton-acces', $request->header('Authorization')[0]);
        $this->assertSame(str_repeat('b', 40), $request['message']['token']);
        $this->assertSame($alice->name, $request['message']['notification']['title']);
        $this->assertSame('Bonjour Bob', $request['message']['notification']['body']);
        $this->assertStringEndsWith('/messagerie/'.$conversation->id, $request['message']['data']['url']);
        $this->assertSame((string) $conversation->id, $request['message']['data']['conversation']);

        // Jeton d'accès demandé avec une assertion JWT signée.
        $oauth = collect(Http::recorded())->map(fn ($pair) => $pair[0])->first(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com'));
        $this->assertCount(3, explode('.', $oauth['assertion']));
    }

    public function test_group_and_voice_messages_use_the_group_title_and_a_neutral_preview(): void
    {
        $this->enableFcm();
        Storage::fake('local');
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-acces']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        [$alice, $bob, $carol, $eve] = User::factory()->count(4)->create();
        $group = Messenger::createGroup($alice->fresh(), 'Équipe', [$bob->id, $carol->id, $eve->id]);
        Messenger::leave($group, $eve);
        foreach ([$bob, $carol, $eve] as $index => $member) {
            $this->device($member, str_repeat((string) $index, 40));
        }

        $this->actingAs($alice)->postJson(route('messenger.send', $group), ['audio' => UploadedFile::fake()->create('v.webm', 20, 'audio/webm'), 'audio_seconds' => 5])->assertCreated();

        $sent = $this->sentToFcm();
        $this->assertCount(2, $sent);
        $this->assertSame(['Équipe'], $sent->map(fn ($r) => $r['message']['notification']['title'])->unique()->values()->all());
        $this->assertSame([$alice->name.' : Message vocal'], $sent->map(fn ($r) => $r['message']['notification']['body'])->unique()->values()->all());
        $this->assertEqualsCanonicalizing([str_repeat('0', 40), str_repeat('1', 40)], $sent->map(fn ($r) => $r['message']['token'])->all());
    }

    public function test_expired_tokens_are_removed_and_failures_never_break_the_send(): void
    {
        $this->enableFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-acces']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.']], 404),
        ]);

        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = Messenger::direct($alice->fresh(), $bob->fresh());
        $this->device($bob, str_repeat('b', 40));

        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Coucou'])->assertCreated();

        $this->assertSame(0, PushDevice::query()->count());
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_an_unreachable_firebase_never_breaks_the_send(): void
    {
        $this->enableFcm();
        Http::fake(['*' => Http::response('erreur', 500)]);

        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = Messenger::direct($alice->fresh(), $bob->fresh());
        $this->device($bob, str_repeat('b', 40));

        $this->actingAs($alice)->postJson(route('messenger.send', $conversation), ['body' => 'Coucou'])->assertCreated();

        $this->assertSame(1, PushDevice::query()->count());
    }
}
