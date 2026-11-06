<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            // Prénom / nom suivent le nom complet, y compris quand un test impose `name`.
            'first_name' => fn (array $attributes): ?string => User::splitName($attributes['name'])[0],
            'last_name' => fn (array $attributes): ?string => User::splitName($attributes['name'])[1],
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Parcours d'intégration terminé : les tests qui l'exercent utilisent `inOnboarding()`.
            'onboarding_done_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Compte neuf : il doit encore passer par le parcours d'intégration (e-mail déjà vérifié).
     */
    public function inOnboarding(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_done_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
