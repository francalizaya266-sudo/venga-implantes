<?php

namespace Database\Factories;

use App\Enums\UserRole;
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'must_change_password' => false,
            'remember_token' => Str::random(10),
            'role' => UserRole::Reception,
            'is_active' => true,
            // El panel exige el segundo factor: por defecto el usuario ya lo activó (secreto fijo de prueba).
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ];
    }

    /** El administrador dejó de pedirle la doble verificación: entra solo con su contraseña. */
    public function skippingDoubleCheck(): static
    {
        return $this->state(fn () => ['skips_double_check' => true]);
    }

    /** Todavía no activó el segundo factor: al entrar al panel solo ve la pantalla para hacerlo. */
    public function withoutMfa(): static
    {
        return $this->state(fn () => [
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
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

    public function admin(bool $isDentist = false): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin, 'is_dentist' => $isDentist]);
    }

    public function reception(): static
    {
        return $this->state(fn () => ['role' => UserRole::Reception]);
    }

    public function doctor(): static
    {
        return $this->state(fn () => ['role' => UserRole::Doctor]);
    }
}
