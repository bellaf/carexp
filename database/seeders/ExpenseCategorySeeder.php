<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['key' => 'fuel', 'name' => 'Fuel'],
            ['key' => 'maintenance', 'name' => 'Maintenance'],
            ['key' => 'repairs', 'name' => 'Repairs'],
            ['key' => 'tires', 'name' => 'Tires'],
            ['key' => 'insurance', 'name' => 'Insurance'],
            ['key' => 'registration_dmv', 'name' => 'Registration/DMV'],
            ['key' => 'parking', 'name' => 'Parking'],
            ['key' => 'tolls', 'name' => 'Tolls'],
            ['key' => 'car_wash_detailing', 'name' => 'Car Wash/Detailing'],
            ['key' => 'loan_lease_payment', 'name' => 'Loan/Lease Payment'],
            ['key' => 'accessories_upgrades', 'name' => 'Accessories/Upgrades'],
            ['key' => 'inspection_emissions', 'name' => 'Inspection/Emissions'],
            ['key' => 'other', 'name' => 'Other'],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::query()->updateOrCreate(
                ['key' => $category['key']],
                ['name' => $category['name'], 'is_system' => true],
            );
        }
    }
}
