<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'marca-de-demostracion'],
            [
                'name' => 'Marca de demostración',
                'description' => 'Registro de ejemplo para comprobar la gestión inicial de marcas.',
                'tone_of_voice' => 'Cercano y claro',
                'target_audience' => 'Público de demostración',
                'social_channels' => ['Instagram', 'LinkedIn'],
                'restrictions' => ['No usar información no verificada.'],
            ],
        );

        User::query()->where('email', 'test@example.com')->first()?->brands()->syncWithoutDetaching([
            $brand->id => ['role' => 'owner'],
        ]);
    }
}
