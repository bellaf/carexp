<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['key' => 'fuel_expense', 'name' => 'Fuel', 'group' => 'expense'],
            ['key' => 'maintenance_expense', 'name' => 'Maintenance', 'group' => 'expense'],
            ['key' => 'repairs_expense', 'name' => 'Repairs', 'group' => 'expense'],
            ['key' => 'tires_expense', 'name' => 'Tires', 'group' => 'expense'],
            ['key' => 'insurance_expense', 'name' => 'Insurance', 'group' => 'expense'],
            ['key' => 'tax_registration_expense', 'name' => 'Tax/Registration', 'group' => 'expense'],
            ['key' => 'parking_expense', 'name' => 'Parking', 'group' => 'expense'],
            ['key' => 'tolls_expense', 'name' => 'Tolls', 'group' => 'expense'],
            ['key' => 'cleaning_expense', 'name' => 'Cleaning', 'group' => 'expense'],
            ['key' => 'accessories_expense', 'name' => 'Accessories', 'group' => 'expense'],
            ['key' => 'other_expense', 'name' => 'Other Expense', 'group' => 'expense'],
            ['key' => 'company_car_allowance_income', 'name' => 'Company Car Allowance', 'group' => 'income'],
            ['key' => 'company_business_fuel_tolls_income', 'name' => 'Company Business Fuel & Tolls Reimbursement', 'group' => 'income'],
        ];

        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(
                ['key' => $account['key']],
                [
                    'user_id' => null,
                    'name' => $account['name'],
                    'group' => $account['group'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
