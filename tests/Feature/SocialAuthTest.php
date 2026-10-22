<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    private function enable(string ...$providers): void
    {
        foreach ($providers as $provider) {
            config(["services.{$provider}.client_id" => 'id', "services.{$provider}.client_secret" => 'secret']);
        }
    }

    private function fakeProvider(string $provider, array $raw, ?string $email = 'jean@example.com'): void
    {
        $socialUser = (new SocialiteUser)->map([
            'id' => 'abc123',
            'name' => trim(($raw['given_name'] ?? '').' '.($raw['family_name'] ?? '')),
            'email' => $email,
        ]);
        $socialUser->user = $raw;

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($socialUser);

        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('driver')->with($provider)->andReturn($driver);

        $this->app->instance(Factory::class, $factory);
    }

    public function test_login_and_register_pages_show_the_network_buttons(): void
    {
        $this->get('/connexion')->assertOk()
            ->assertSee('Se connecter avec Google')
            ->assertSee('Se connecter avec Facebook')
            ->assertSee('Se connecter avec LinkedIn')
            ->assertSee('/auth/linkedin-openid/redirect', false);

        $this->get('/inscription')->assertOk()->assertSee("avec Facebook");
    }

    public function test_unconfigured_provider_returns_to_login_with_a_message(): void
    {
        $this->get('/auth/google/redirect')
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors('email');
        $this->get('/auth/google/callback')->assertRedirect('/connexion');
    }

    public function test_unknown_provider_is_not_found(): void
    {
        $this->get('/auth/twitter/redirect')->assertNotFound();
    }

    public function test_callback_creates_a_member_and_logs_in(): void
    {
        $this->enable('google');
        $this->fakeProvider('google', ['given_name' => 'Jean', 'family_name' => 'Dupont', 'email_verified' => true]);

        $this->get('/auth/google/callback')->assertRedirect('/feed');

        $user = User::query()->where('email', 'jean@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Jean', $user->first_name);
        $this->assertSame('Dupont', $user->last_name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->profile);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'abc123']);
    }

    public function test_callback_links_existing_user_with_verified_email(): void
    {
        $this->enable('google');
        $existing = User::factory()->create(['email' => 'jean@example.com']);
        $this->fakeProvider('google', ['given_name' => 'Jean', 'family_name' => 'Dupont', 'email_verified' => true]);

        $this->get('/auth/google/callback')->assertRedirect('/feed');

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, SocialAccount::query()->where('user_id', $existing->id)->count());
    }

    public function test_callback_refuses_to_link_an_unverified_email(): void
    {
        $this->enable('google');
        User::factory()->create(['email' => 'jean@example.com']);
        $this->fakeProvider('google', ['given_name' => 'Jean', 'email_verified' => false]);

        $this->get('/auth/google/callback')->assertRedirect('/connexion');

        $this->assertGuest();
        $this->assertSame(0, SocialAccount::query()->count());
    }

    public function test_callback_refuses_when_no_email_is_provided(): void
    {
        $this->enable('facebook');
        $this->fakeProvider('facebook', ['name' => 'Jean Dupont'], email: null);

        $this->get('/auth/facebook/callback')->assertRedirect('/connexion');

        $this->assertGuest();
    }

    public function test_deactivated_account_cannot_log_in_with_a_network(): void
    {
        $this->enable('google');
        User::factory()->create(['email' => 'jean@example.com', 'is_active' => false]);
        $this->fakeProvider('google', ['given_name' => 'Jean', 'email_verified' => true]);

        $this->get('/auth/google/callback')->assertRedirect('/connexion');

        $this->assertGuest();
    }
}
