<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('test-password-not-for-production'),
            'account_type' => 'candidate',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function employer(): static
    {
        return $this->state(fn () => ['account_type' => 'employer']);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['account_type' => 'staff']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended', 'suspended_at' => now()]);
    }
}
