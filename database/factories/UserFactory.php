<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'username' => $this->faker->unique()->userName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->optional()->e164PhoneNumber(),

            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),

            'is_staff' => false,

            'email_verified_at' => now(),
            'phone_verified_at' => null,

            'password' => bcrypt('password'), // или Hash::make('password')
            'remember_token' => Str::random(10),
        ];
    }
}