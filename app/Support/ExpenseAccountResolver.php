<?php

namespace App\Support;

use App\Models\Account;
use App\Models\ExpenseCategory;

class ExpenseAccountResolver
{
    public function accountForCategory(?ExpenseCategory $category): Account
    {
        $accountKey = $this->accountKeyForCategory($category);

        return Account::query()->firstOrCreate(
            ['key' => $accountKey],
            [
                'user_id' => null,
                'name' => $this->defaultAccountName($accountKey),
                'group' => 'expense',
                'is_system' => true,
                'is_active' => true,
            ],
        );
    }

    public function accountKeyForCategory(?ExpenseCategory $category): string
    {
        return match ($category?->key) {
            'fuel' => 'fuel_expense',
            'maintenance' => 'maintenance_expense',
            'repairs' => 'repairs_expense',
            'tires' => 'tires_expense',
            'insurance' => 'insurance_expense',
            'registration_dmv' => 'tax_registration_expense',
            'parking' => 'parking_expense',
            'tolls' => 'tolls_expense',
            default => 'other_expense',
        };
    }

    public function defaultAccountName(string $accountKey): string
    {
        return match ($accountKey) {
            'fuel_expense' => 'Fuel',
            'maintenance_expense' => 'Maintenance',
            'repairs_expense' => 'Repairs',
            'tires_expense' => 'Tires',
            'insurance_expense' => 'Insurance',
            'tax_registration_expense' => 'Tax/Registration',
            'parking_expense' => 'Parking',
            'tolls_expense' => 'Tolls',
            default => 'Other Expense',
        };
    }
}
