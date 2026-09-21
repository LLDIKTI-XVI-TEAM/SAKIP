<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'keycloak_id' => (string) Str::uuid(),
            'nama' => fake()->name(),
            'email' => fake()->safeEmail(),
            'is_active' => false,
        ];
    }
}
