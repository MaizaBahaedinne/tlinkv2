<?php

namespace Tests\Feature;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushTestCommandTest extends TestCase
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
        file_put_contents($this->credentialsFile, json_encode(['project_id' => 'tlink-test', 'client_email' => 'push@tlink-test.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['services.fcm.credentials' => $this->credentialsFile]);
    }

    public function test_it_reports_the_state_without_an_email(): void
    {
        $this->artisan('push:test')->expectsOutputToContain('Firebase configuré : NON')->assertSuccessful();
    }

    public function test_it_warns_when_the_account_has_no_device(): void
    {
        $user = User::factory()->create();

        $this->artisan('push:test', ['email' => $user->email])->expectsOutputToContain('Aucun appareil')->assertFailed();
    }

    public function test_it_sends_a_test_notification_and_reports_each_device(): void
    {
        $this->enableFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['name' => 'ok'], 200)
                ->push(['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'jeton invalide']], 400),
        ]);

        $user = User::factory()->create();
        PushDevice::query()->create(['user_id' => $user->id, 'token' => str_repeat('a', 40), 'platform' => 'android', 'last_seen_at' => now()]);
        PushDevice::query()->create(['user_id' => $user->id, 'token' => str_repeat('b', 40), 'platform' => 'android', 'last_seen_at' => now()]);

        $this->artisan('push:test', ['email' => $user->email])
            ->expectsOutputToContain('envoyé (HTTP 200)')
            ->expectsOutputToContain('refusé (HTTP 400) INVALID_ARGUMENT jeton invalide')
            ->assertFailed();
    }
}
