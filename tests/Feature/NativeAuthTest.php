<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NativeAuthTest extends TestCase
{
    use RefreshDatabase;

    private const APP = 'com.tunivisions.stores';

    private string $verifier;

    private string $challenge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = str_repeat('ab', 32);
        $this->challenge = hash('sha256', $this->verifier);
    }

    private function startUrl(array $extra = []): string
    {
        return route('native.start', ['app' => self::APP, 'challenge' => $this->challenge] + $extra);
    }

    private function issueToken(User $user): string
    {
        $html = $this->actingAs($user)->get($this->startUrl())->assertOk()->getContent();
        preg_match('/'.preg_quote(self::APP, '/').':\/\/auth\?token=([A-Za-z0-9]{64})/', $html, $match);

        return $match[1];
    }

    public function test_guest_is_sent_to_login_and_returns_to_start_afterwards(): void
    {
        $this->get($this->startUrl())->assertRedirect(route('login'));

        $this->assertSame($this->startUrl(), session('url.intended'));
    }

    public function test_guest_can_start_with_a_social_provider(): void
    {
        $this->get($this->startUrl(['provider' => 'google']))->assertRedirect(route('social.redirect', ['provider' => 'google']));
    }

    public function test_signed_in_browser_receives_a_deep_link_to_the_app(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->startUrl())
            ->assertOk()
            ->assertSee(self::APP.'://auth?token=', false);
    }

    public function test_unknown_app_or_invalid_challenge_is_refused(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('native.start', ['app' => 'evil.app', 'challenge' => $this->challenge]))->assertNotFound();
        $this->get(route('native.start', ['app' => self::APP, 'challenge' => 'abc']))->assertStatus(422);
    }

    public function test_token_with_the_verifier_signs_the_app_in_and_lands_on_its_section(): void
    {
        $user = User::factory()->create();
        $token = $this->issueToken($user);
        auth()->logout();
        $this->flushSession();

        $this->get(route('native.consume', ['token' => $token, 'v' => $this->verifier]))->assertRedirect(url('/boutique'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_token_is_refused_without_the_right_verifier_and_is_burnt(): void
    {
        $token = $this->issueToken(User::factory()->create());
        auth()->logout();
        $this->flushSession();

        $this->get(route('native.consume', ['token' => $token, 'v' => str_repeat('cd', 32)]))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('native.consume', ['token' => $token, 'v' => $this->verifier]))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_token_is_single_use(): void
    {
        $token = $this->issueToken(User::factory()->create());
        auth()->logout();
        $this->flushSession();

        $this->get(route('native.consume', ['token' => $token, 'v' => $this->verifier]))->assertRedirect(url('/boutique'));
        auth()->logout();
        $this->flushSession();

        $this->get(route('native.consume', ['token' => $token, 'v' => $this->verifier]))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_expired_token_and_deactivated_account_are_refused(): void
    {
        $user = User::factory()->create();
        $token = $this->issueToken($user);
        auth()->logout();
        $this->flushSession();

        $user->forceFill(['is_active' => false])->save();
        $this->get(route('native.consume', ['token' => $token, 'v' => $this->verifier]))->assertRedirect(route('login'));
        $this->assertGuest();

        Cache::flush();
        $this->get(route('native.consume', ['token' => str_repeat('a', 64), 'v' => $this->verifier]))->assertRedirect(route('login'));
    }

    public function test_login_page_embeds_the_native_script_and_the_sso_button(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="native-auth"', false)
            ->assertSee('data-native-sso', false);
    }
}
