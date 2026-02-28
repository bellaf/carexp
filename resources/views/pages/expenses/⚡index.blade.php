<?php

use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerEntry;
use App\Support\CurrencyFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public bool $confirmingDelete = false;
    public ?int $editingExpenseId = null;
    public ?int $editingCategoryId = null;
    public string $categoryName = '';

    /**
     * @var array<string, mixed>
     */
    public array $form = [];
    public ?string $filterCategoryId = null;
    public string $filterPeriod = 'this_month';

    public function mount(): void
    {
        $this->resetForm();
    }

    public function startCreating(): void
    {
        $this->editingExpenseId = null;
        $this->resetForm();

        if ($this->categories->isNotEmpty()) {
            $this->form['expense_category_id'] = (string) $this->categories->first()->id;
        }

        if ($this->cars->isNotEmpty()) {
            $this->form['car_id'] = (string) $this->cars->first()->id;
        }

        $this->showForm = true;
    }

    public function editExpense(int $expenseId): void
    {
        $expense = Auth::user()->expenses()->findOrFail($expenseId);

        $this->editingExpenseId = $expense->id;
        $this->form = [
            'car_id' => (string) $expense->car_id,
            'expense_category_id' => (string) $expense->expense_category_id,
            'amount' => (string) $expense->amount,
            'expense_date' => $expense->expense_date->format('Y-m-d'),
            'odometer' => $expense->odometer ?? '',
            'vendor' => $expense->vendor ?? '',
            'notes' => $expense->notes ?? '',
            'tags' => implode(', ', $expense->tags ?? []),
        ];

        $this->showForm = true;
        $this->confirmingDelete = false;
    }

    public function saveExpense(): void
    {
        $form = $this->validate($this->expenseRules(), $this->expenseMessages())['form'];
        $attributes = $this->normalizeExpenseAttributes($form);

        DB::transaction(function () use ($attributes): void {
            if ($this->editingExpenseId !== null) {
                $expense = Auth::user()->expenses()->findOrFail($this->editingExpenseId);
                $expense->update($attributes);
            } else {
                $expense = Auth::user()->expenses()->create($attributes);
            }

            $this->syncLedgerEntry($expense);
        });

        $this->cancelForm();
        $this->dispatch('expense-saved');
    }

    public function deleteExpense(int $expenseId): void
    {
        DB::transaction(function () use ($expenseId): void {
            $expense = Auth::user()->expenses()->findOrFail($expenseId);

            if ($expense->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($expense->ledger_entry_id)->delete();
            }

            $expense->delete();
        });
    }

    public function clearFilters(): void
    {
        $this->filterCategoryId = null;
        $this->filterPeriod = 'this_month';
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingExpenseId = null;
        $this->confirmingDelete = false;
        $this->resetForm();
    }

    public function confirmDeleteEditing(): void
    {
        if ($this->editingExpenseId === null) {
            return;
        }

        $this->confirmingDelete = true;
    }

    public function cancelDeleteEditing(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteEditingExpense(): void
    {
        if ($this->editingExpenseId === null) {
            return;
        }

        $this->deleteExpense($this->editingExpenseId);
        $this->cancelForm();
    }

    public function startCreatingCategory(): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
    }

    public function editCategory(int $categoryId): void
    {
        $category = ExpenseCategory::query()->findOrFail($categoryId);

        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
    }

    public function saveCategory(): void
    {
        $validated = $this->validate([
            'categoryName' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingCategoryId !== null) {
            $category = ExpenseCategory::query()->findOrFail($this->editingCategoryId);
            $category->update(['name' => $validated['categoryName']]);
        } else {
            $baseKey = Str::of($validated['categoryName'])->lower()->snake()->toString();
            $baseKey = $baseKey !== '' ? $baseKey : 'custom_category';
            $key = $baseKey;
            $suffix = 1;

            while (ExpenseCategory::query()->where('key', $key)->exists()) {
                $key = $baseKey.'_'.$suffix;
                $suffix++;
            }

            $category = ExpenseCategory::query()->create([
                'key' => $key,
                'name' => $validated['categoryName'],
                'is_system' => false,
            ]);
        }

        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->dispatch('category-saved');

        if ($this->form['expense_category_id'] === '') {
            $this->form['expense_category_id'] = (string) $category->id;
        }
    }

    public function cancelCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
    }

    public function formatCurrency(float|int|string|null $amount): string
    {
        return CurrencyFormatter::format($amount, Auth::user()->preferred_currency);
    }

    #[Computed]
    public function cars(): Collection
    {
        return Auth::user()->cars()->where('is_archived', false)->orderBy('make')->orderBy('model')->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        return ExpenseCategory::query()->orderBy('name')->get();
    }

    #[Computed]
    public function expenses(): Collection
    {
        $periodStartDate = match ($this->filterPeriod) {
            'last_month' => now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
            'year_to_date' => now()->startOfYear()->format('Y-m-d'),
            'all_time' => null,
            default => now()->startOfMonth()->format('Y-m-d'),
        };

        $periodEndDate = match ($this->filterPeriod) {
            'last_month' => now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'),
            default => null,
        };

        return Auth::user()
            ->expenses()
            ->with(['car', 'category'])
            ->when($this->filterCategoryId, fn ($query) => $query->where('expense_category_id', (int) $this->filterCategoryId))
            ->when($periodStartDate !== null, fn ($query) => $query->whereDate('expense_date', '>=', $periodStartDate))
            ->when($periodEndDate !== null, fn ($query) => $query->whereDate('expense_date', '<=', $periodEndDate))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->expenses->sum('amount');
    }

    /**
     * @return array<string, mixed>
     */
    protected function expenseRules(): array
    {
        return [
            'form.car_id' => [
                'required',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'form.expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'form.amount' => ['required', 'numeric', 'min:0.01'],
            'form.expense_date' => ['required', 'date'],
            'form.odometer' => ['nullable', 'integer', 'min:0'],
            'form.vendor' => ['nullable', 'string', 'max:255'],
            'form.notes' => ['nullable', 'string'],
            'form.tags' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function expenseMessages(): array
    {
        return [
            'form.car_id.required' => 'Please select a car.',
            'form.expense_category_id.required' => 'Please select a category.',
            'form.amount.required' => 'Amount is required.',
            'form.expense_date.required' => 'Date is required.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function normalizeExpenseAttributes(array $form): array
    {
        foreach (['odometer', 'vendor', 'notes'] as $field) {
            if ($form[$field] === '') {
                $form[$field] = null;
            }
        }

        $form['tags'] = collect(explode(',', (string) $form['tags']))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->values()
            ->all();

        if ($form['tags'] === []) {
            $form['tags'] = null;
        }

        return $form;
    }

    protected function syncLedgerEntry(Expense $expense): void
    {
        $account = $this->accountForExpenseCategory($expense->category);

        $entryAttributes = [
            'user_id' => Auth::id(),
            'car_id' => $expense->car_id,
            'account_id' => $account->id,
            'entry_date' => $expense->expense_date->format('Y-m-d'),
            'entry_type' => 'expense',
            'amount' => $expense->amount,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'reference' => $expense->vendor,
            'notes' => $expense->notes,
        ];

        $entry = $expense->ledger_entry_id !== null
            ? Auth::user()->ledgerEntries()->findOrFail($expense->ledger_entry_id)
            : new LedgerEntry();

        $entry->fill($entryAttributes);
        $entry->save();

        if ($expense->ledger_entry_id !== $entry->id) {
            $expense->update(['ledger_entry_id' => $entry->id]);
        }
    }

    protected function accountForExpenseCategory(?ExpenseCategory $category): Account
    {
        $accountKey = match ($category?->key) {
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

    protected function defaultAccountName(string $accountKey): string
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

    protected function resetForm(): void
    {
        $this->form = [
            'car_id' => '',
            'expense_category_id' => '',
            'amount' => '',
            'expense_date' => now()->format('Y-m-d'),
            'odometer' => '',
            'vendor' => '',
            'notes' => '',
            'tags' => '',
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Expenses') }}</flux:heading>
            <flux:subheading>{{ __('Log and review your car-related costs.') }}</flux:subheading>
        </div>

        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
            <flux:modal.trigger name="manage-expense-categories">
                <flux:button class="w-full sm:w-auto" variant="ghost" wire:click="startCreatingCategory">
                    {{ __('Edit Categories') }}
                </flux:button>
            </flux:modal.trigger>

            <flux:button class="w-full sm:w-auto" variant="primary" wire:click="startCreating" :disabled="$this->cars->isEmpty() || $this->categories->isEmpty()">
                {{ __('Add Expense') }}
            </flux:button>
        </div>
    </div>

    @if ($this->cars->isEmpty())
        <flux:card>
            <flux:text>{{ __('Add a car first before creating expenses.') }}</flux:text>
        </flux:card>
    @endif

    @if ($this->categories->isEmpty())
        <flux:card>
            <flux:text>{{ __('No expense categories found. Run database seeders to add defaults.') }}</flux:text>
        </flux:card>
    @endif

    <flux:modal :closable="false" name="manage-expense-categories" class="border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[42rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div>
                <flux:heading>{{ __('Manage Expense Categories') }}</flux:heading>
                <flux:subheading>{{ __('Add custom categories or rename existing ones.') }}</flux:subheading>
            </div>

            <form wire:submit="saveCategory" class="space-y-3">
                <flux:input
                    wire:model="categoryName"
                    :label="$editingCategoryId ? __('Edit Category Name') : __('New Category Name')"
                    type="text"
                    required
                />

                <div class="flex items-center gap-2">
                    <flux:button type="submit" variant="primary">{{ __('Save Category') }}</flux:button>
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="cancelCategoryForm">{{ __('Close') }}</flux:button>
                    </flux:modal.close>

                    <x-action-message on="category-saved">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>

            <div class="max-h-80 space-y-2 overflow-auto rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                @foreach ($this->categories as $category)
                    <div class="flex items-center justify-between rounded-md border border-zinc-200 p-2 dark:border-zinc-700">
                        <flux:text>{{ $category->name }}</flux:text>

                        <flux:button variant="ghost" wire:click="editCategory({{ $category->id }})">{{ __('Edit') }}</flux:button>
                    </div>
                @endforeach
            </div>
        </div>
    </flux:modal>

    <flux:modal :closable="false" wire:model="showForm" class="max-h-[90vh] overflow-y-auto border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[48rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ $editingExpenseId ? __('Edit Expense') : __('Add Expense') }}</flux:heading>
                    <flux:subheading>{{ __('Create or update manual expense entries.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveExpense" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="form.car_id" :label="__('Car')" required>
                        <flux:select.option value="">{{ __('Select car') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="form.expense_category_id" :label="__('Category')" required>
                        <flux:select.option value="">{{ __('Select category') }}</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.amount" :label="__('Amount')" type="number" min="0.01" step="0.01" required />
                    <flux:input wire:model="form.expense_date" :label="__('Date')" type="date" required />
                    <flux:input wire:model="form.odometer" :label="__('Odometer')" type="number" min="0" step="1" />
                    <flux:input wire:model="form.vendor" :label="__('Vendor')" type="text" />
                </div>

                <flux:input wire:model="form.tags" :label="__('Tags (comma separated)')" type="text" />
                <flux:input wire:model="form.notes" :label="__('Notes')" type="text" />

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="primary">{{ __('Save Expense') }}</flux:button>
                        <x-action-message on="expense-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($editingExpenseId !== null && ! $confirmingDelete)
                            <flux:button type="button" variant="danger" wire:click="confirmDeleteEditing">{{ __('Delete') }}</flux:button>
                        @elseif ($editingExpenseId !== null && $confirmingDelete)
                            <flux:text class="text-red-600 dark:text-red-400">{{ __('Confirm delete this expense?') }}</flux:text>
                            <flux:button type="button" variant="danger" wire:click="deleteEditingExpense">{{ __('Confirm Delete') }}</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="cancelDeleteEditing">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:card class="space-y-4">
        <div class="space-y-3 md:hidden">
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="filterPeriod" :label="__('Period')">
                    <flux:select.option value="this_month">{{ __('This Month') }}</flux:select.option>
                    <flux:select.option value="last_month">{{ __('Last Month') }}</flux:select.option>
                    <flux:select.option value="year_to_date">{{ __('Year to Date') }}</flux:select.option>
                    <flux:select.option value="all_time">{{ __('All Time') }}</flux:select.option>
                </flux:select>
            </div>

            <details class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                <summary class="cursor-pointer text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('More Filters') }}</summary>
                <div class="mt-3">
                    <flux:select wire:model.live="filterCategoryId" :label="__('Filter Category')">
                        <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </details>
        </div>

        <div class="hidden flex-wrap items-end gap-3 md:flex">
            <div class="w-full sm:w-64">
                <flux:select wire:model.live="filterCategoryId" :label="__('Filter Category')">
                    <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-full sm:w-56">
                <flux:select wire:model.live="filterPeriod" :label="__('Period')">
                    <flux:select.option value="this_month">{{ __('This Month') }}</flux:select.option>
                    <flux:select.option value="last_month">{{ __('Last Month') }}</flux:select.option>
                    <flux:select.option value="year_to_date">{{ __('Year to Date') }}</flux:select.option>
                    <flux:select.option value="all_time">{{ __('All Time') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        <flux:text>{{ __('Filtered total') }}: <strong>{{ $this->formatCurrency($this->filteredTotal) }}</strong></flux:text>
        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Tap any expense to edit it.') }}</flux:text>
    </flux:card>

    @if ($this->expenses->isEmpty())
        <flux:card>
            <flux:text>{{ __('No expenses found for the selected filters.') }}</flux:text>
        </flux:card>
    @else
        <div class="space-y-3 md:hidden">
            @foreach ($this->expenses as $expense)
                <button
                    type="button"
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                    wire:click="editExpense({{ $expense->id }})"
                    wire:key="expense-card-{{ $expense->id }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $expense->category->name }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $expense->expense_date->format('d-m-Y') }}</div>
                        </div>
                        <div class="text-right font-semibold">{{ $this->formatCurrency($expense->amount) }}</div>
                    </div>
                    <dl class="mt-3 grid gap-2 text-sm">
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Vendor') }}</dt>
                            <dd>{{ $expense->vendor ?: __('N/A') }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Odometer') }}</dt>
                                <dd>{{ $expense->odometer !== null ? number_format((float) $expense->odometer) : __('N/A') }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Tags') }}</dt>
                                <dd>{{ implode(', ', $expense->tags ?? []) ?: __('N/A') }}</dd>
                            </div>
                        </div>
                    </dl>
                </button>
            @endforeach
        </div>
        <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Date') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Category') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Vendor') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Odometer') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Tags') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->expenses as $expense)
                        <tr
                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                            tabindex="0"
                            wire:click="editExpense({{ $expense->id }})"
                            wire:key="expense-row-{{ $expense->id }}"
                        >
                            <td class="px-3 py-2">{{ $expense->expense_date->format('d-m-Y') }}</td>
                            <td class="px-3 py-2">{{ $expense->category->name }}</td>
                            <td class="px-3 py-2">{{ $expense->vendor ?: __('N/A') }}</td>
                            <td class="px-3 py-2">{{ $expense->odometer !== null ? number_format((float) $expense->odometer) : __('N/A') }}</td>
                            <td class="px-3 py-2">{{ implode(', ', $expense->tags ?? []) ?: __('N/A') }}</td>
                            <td class="px-3 py-2 text-right">{{ $this->formatCurrency($expense->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
