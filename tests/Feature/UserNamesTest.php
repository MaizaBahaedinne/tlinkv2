<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_name_cuts_at_the_first_space(): void
    {
        $this->assertSame(['Amine', 'Ben Salah'], User::splitName('  Amine   Ben Salah '));
        $this->assertSame(['Sonia', null], User::splitName('Sonia'));
        $this->assertSame([null, null], User::splitName('   '));
    }

    public function test_full_name_follows_first_and_last_name(): void
    {
        $user = User::factory()->create(['name' => 'Amine Ben Salah']);
        $this->assertSame('Amine', $user->first_name);
        $this->assertSame('Ben Salah', $user->last_name);

        $user->update(['first_name' => 'Yassine', 'last_name' => 'Trabelsi']);
        $this->assertSame('Yassine Trabelsi', $user->fresh()->name);

        $user->update(['name' => 'Karim Gharbi']);
        $user->refresh();
        $this->assertSame('Karim', $user->first_name);
        $this->assertSame('Gharbi', $user->last_name);
    }

    public function test_web_registration_requires_and_stores_first_and_last_name(): void
    {
        $payload = ['email' => 'nouveau@example.test', 'password' => 'Mot-De-Passe-Solide-42', 'password_confirmation' => 'Mot-De-Passe-Solide-42'];

        $this->post('/inscription', $payload + ['first_name' => 'Leila'])->assertSessionHasErrors('last_name');
        $this->assertDatabaseMissing('users', ['email' => 'nouveau@example.test']);

        $this->post('/inscription', $payload + ['first_name' => 'Leila', 'last_name' => 'Ben Ali'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'nouveau@example.test', 'first_name' => 'Leila', 'last_name' => 'Ben Ali', 'name' => 'Leila Ben Ali',
        ]);
    }

    public function test_api_registration_accepts_first_last_and_legacy_full_name(): void
    {
        $password = ['password' => 'Mot-De-Passe-Solide-42', 'password_confirmation' => 'Mot-De-Passe-Solide-42'];

        $this->postJson('/api/v1/auth/register', ['first_name' => 'Nour', 'last_name' => 'Jlassi', 'email' => 'nour@example.test'] + $password)
            ->assertCreated()->assertJsonPath('data.name', 'Nour Jlassi')->assertJsonPath('data.first_name', 'Nour');

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/register', ['name' => 'Sami Ben Youssef', 'email' => 'sami@example.test'] + $password)
            ->assertCreated()->assertJsonPath('data.first_name', 'Sami')->assertJsonPath('data.last_name', 'Ben Youssef');

        $this->postJson('/api/v1/auth/register', ['email' => 'vide@example.test'] + $password)->assertUnprocessable();
    }

    public function test_migration_backfills_existing_users(): void
    {
        $migration = require database_path('migrations/2026_10_23_200000_split_user_names.php');
        $migration->down();

        DB::table('users')->insert([
            ['name' => 'Amine Ben Salah', 'email' => 'a@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sonia', 'email' => 'b@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration->up();

        $this->assertDatabaseHas('users', ['email' => 'a@example.test', 'first_name' => 'Amine', 'last_name' => 'Ben Salah']);
        $this->assertDatabaseHas('users', ['email' => 'b@example.test', 'first_name' => 'Sonia', 'last_name' => null]);
    }
}
