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
    /** Klartext für Tests; erfüllt die App-Passwort-Policy (min. 12 Zeichen, siehe AppServiceProvider). */
    public const DEFAULT_PASSWORD = 'secure-test-passphrase';

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
        $username = strtolower(fake()->unique()->userName());

        return [
            'username' => $username,
            'email' => fake()->unique()->safeEmail(),
            'role' => 'community_member',
            'password' => static::$password ??= Hash::make(self::DEFAULT_PASSWORD),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'email_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
