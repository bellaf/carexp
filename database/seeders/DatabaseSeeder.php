<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AccountSeeder::class);
        $this->call(ExpenseCategorySeeder::class);

        User::query()->firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'is_approved' => true,
            'approved_at' => now(),
            'email_verified_at' => now(),
            'preferred_currency' => 'USD',
            'measurement_system' => 'imperial',
            'volume_unit' => 'gallons',
            'ui_theme' => 'classic',
            'timezone' => 'UTC',
        ]);
    }
}
