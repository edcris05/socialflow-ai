<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->optional()->paragraph(),
            'tone_of_voice' => fake()->optional()->randomElement(['cercano', 'profesional', 'directo']),
            'target_audience' => fake()->optional()->sentence(6),
            'social_channels' => fake()->optional()->randomElements(['Instagram', 'Facebook', 'LinkedIn'], 2),
            'restrictions' => fake()->optional()->sentences(2),
        ];
    }
}
