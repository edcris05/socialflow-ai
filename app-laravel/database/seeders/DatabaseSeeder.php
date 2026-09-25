<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(BrandSeeder::class);

        $user->brands()->syncWithoutDetaching(
            \App\Models\Brand::query()->pluck('id')->mapWithKeys(fn (string $id): array => [$id => ['role' => 'owner']])
        );
    }
}
