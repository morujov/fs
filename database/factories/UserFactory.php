<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        // google_id — числовая строка на 21 знак, как настоящий Google `sub`.
        return [
            'google_id'   => (string) $this->faker->unique()->numerify(str_repeat('#', 21)),
            'name'        => $this->faker->name(),
            'email'       => $this->faker->unique()->safeEmail(),
            'avatar_url'  => 'https://lh3.googleusercontent.com/a/'.Str::random(32),
            'seller_type' => 'private',
            'phone'       => '+346'.$this->faker->numerify('########'),
            'locale'      => $this->faker->randomElement(['es', 'es', 'es', 'en']),
            'status'      => 'active',
        ];
    }

    public function shop(): static
    {
        return $this->state(fn () => ['seller_type' => 'shop']);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => 'blocked']);
    }
}
