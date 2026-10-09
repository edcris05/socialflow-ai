<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\BrandAutopublishingSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandAutopublishingSetting>
 */
class BrandAutopublishingSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'enabled' => false,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => true,
            'enabled_by' => User::factory(),
            'enabled_at' => now(),
        ]);
    }
}
