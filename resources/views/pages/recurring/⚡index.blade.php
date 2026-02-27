<?php

use App\Models\Account;
use App\Support\CurrencyFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public bool $showDetailsModal = false;
    public bool $isModalEditing = false;
    public bool $confirmingDelete = false;
    public ?int $editingRecurringId = null;

    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    public function mount(): void
    {
        $this->ensureDefaultAccounts();
        $this->resetForm();
    }

    public function startCreating(): void
    {
        $this->editingRecurringId = null;
        $this->resetForm();

        if ($this->cars->isNotEmpty()) {
            $this->form['car_id'] = (string) $this->cars->first()->id;
        }

        $this->form['account_id'] = (string) $this->defaultAccountId($this->form['entry_type']);
        $this->showForm = true;
    }

    public function openRecurringDetails(int $recurringId): void
    {
        $recurring = Auth::user()->recurringTransactions()->findOrFail($recurringId);

        $this->editingRecurringId = $recurring->id;
        $this->form = [
            'car_id' => $recurring->car_id !== null ? (string) $recurring->car_id : '',
            'entry_type' => $recurring->entry_type,
            'account_id' => (string) $recurring->account_id,
            'amount' => (string) $recurring->amount,
            'cadence' => $recurring->cadence,
            'next_entry_date' => $recurring->next_entry_date->format('Y-m-d'),
            'end_date' => $recurring->end_date?->format('Y-m-d') ?? '',
            'reference' => $recurring->reference ?? '',
            'notes' => $recurring->notes ?? '',
            'is_active' => $recurring->is_active,
        ];

        $this->showDetailsModal = true;
        $this->isModalEditing = true;
        $this->confirmingDelete = false;
    }

    public function editRecurring(int $recurringId): void
    {
        $this->openRecurringDetails($recurringId);
    }

    public function saveRecurring(): void
    {
        $isFromDetailsModal = $this->showDetailsModal;

        $form = $this->validate($this->rules(), $this->messages())['form'];
        $attributes = $this->normalizeAttributes($form);

        if ($this->editingRecurringId !== null) {
            Auth::user()->recurringTransactions()->findOrFail($this->editingRecurringId)->update($attributes);
        } else {
            Auth::user()->recurringTransactions()->create($attributes);
        }

        if ($isFromDetailsModal) {
            $this->closeDetailsModal();
        } else {
            $this->cancelForm();
        }

        $this->dispatch('recurring-saved');
    }

    public function deleteRecurring(int $recurringId): void
    {
        Auth::user()->recurringTransactions()->findOrFail($recurringId)->delete();
    }

    public function toggleRecurringActive(int $recurringId): void
    {
        $recurring = Auth::user()->recurringTransactions()->findOrFail($recurringId);
        $newActiveState = ! $recurring->is_active;
        $recurring->update(['is_active' => $newActiveState]);

        if ($this->editingRecurringId === $recurringId) {
            $this->form['is_active'] = $newActiveState;
        }
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingRecurringId = null;
        $this->resetForm();
    }

    public function closeDetailsModal(): void
    {
        $this->showDetailsModal = false;
        $this->isModalEditing = false;
        $this->confirmingDelete = false;
        $this->editingRecurringId = null;
        $this->resetForm();
    }

    public function startModalEdit(): void
    {
        if ($this->editingRecurringId === null) {
            return;
        }

        $this->isModalEditing = true;
    }

    public function confirmDeleteInModal(): void
    {
        if ($this->editingRecurringId === null) {
            return;
        }

        $this->confirmingDelete = true;
    }

    public function cancelDeleteInModal(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteFromModal(): void
    {
        if ($this->editingRecurringId === null) {
            return;
        }

        Auth::user()->recurringTransactions()->findOrFail($this->editingRecurringId)->delete();
        $this->closeDetailsModal();
    }

    public function updatedFormEntryType(): void
    {
        $this->form['account_id'] = (string) $this->defaultAccountId($this->form['entry_type']);
    }

    public function runDueEntriesNow(): void
    {
        Artisan::call('app:generate-recurring-transactions');
        $this->dispatch('recurring-generated');
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
    public function recurringTransactions(): Collection
    {
        return Auth::user()
            ->recurringTransactions()
            ->with(['car', 'account'])
            ->orderByDesc('is_active')
            ->orderBy('next_entry_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function accounts(): Collection
    {
        return Account::query()
            ->where('group', $this->form['entry_type'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.car_id' => [
                'nullable',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'form.entry_type' => ['required', Rule::in(['expense', 'income'])],
            'form.account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('group', $this->form['entry_type'])),
            ],
            'form.amount' => ['required', 'numeric', 'min:0.01'],
            'form.cadence' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'form.next_entry_date' => ['required', 'date'],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.next_entry_date'],
            'form.reference' => ['nullable', 'string', 'max:255'],
            'form.notes' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'form.entry_type.required' => 'Type is required.',
            'form.account_id.required' => 'Account is required.',
            'form.amount.required' => 'Amount is required.',
            'form.cadence.required' => 'Cadence is required.',
            'form.next_entry_date.required' => 'Next date is required.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function normalizeAttributes(array $form): array
    {
        foreach (['car_id', 'end_date', 'reference', 'notes'] as $field) {
            if ($form[$field] === '') {
                $form[$field] = null;
            }
        }

        return [
            'car_id' => $form['car_id'] !== null ? (int) $form['car_id'] : null,
            'account_id' => (int) $form['account_id'],
            'entry_type' => $form['entry_type'],
            'amount' => (float) $form['amount'],
            'cadence' => $form['cadence'],
            'next_entry_date' => $form['next_entry_date'],
            'end_date' => $form['end_date'],
            'reference' => $form['reference'],
            'notes' => $form['notes'],
            'is_active' => (bool) $form['is_active'],
        ];
    }

    protected function ensureDefaultAccounts(): void
    {
        Account::query()->updateOrCreate(
            ['key' => 'other_expense'],
            [
                'user_id' => null,
                'name' => 'Other Expense',
                'group' => 'expense',
                'is_system' => true,
                'is_active' => true,
            ],
        );

        Account::query()->updateOrCreate(
            ['key' => 'company_car_allowance_income'],
            [
                'user_id' => null,
                'name' => 'Company Car Allowance',
                'group' => 'income',
                'is_system' => true,
                'is_active' => true,
            ],
        );
    }

    protected function defaultAccountId(string $entryType): int
    {
        $defaultKey = $entryType === 'income' ? 'company_car_allowance_income' : 'other_expense';

        return (int) Account::query()->firstOrCreate(
            ['key' => $defaultKey],
            [
                'user_id' => null,
                'name' => $entryType === 'income' ? 'Company Car Allowance' : 'Other Expense',
                'group' => $entryType,
                'is_system' => true,
                'is_active' => true,
            ],
        )->id;
    }

    protected function resetForm(): void
    {
        $entryType = 'expense';

        $this->form = [
            'car_id' => '',
            'entry_type' => $entryType,
            'account_id' => (string) $this->defaultAccountId($entryType),
            'amount' => '',
            'cadence' => 'monthly',
            'next_entry_date' => now()->format('Y-m-d'),
            'end_date' => '',
            'reference' => '',
            'notes' => '',
            'is_active' => true,
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Recurring Schedules') }}</flux:heading>
            <flux:subheading>{{ __('Manage repeat expenses and reimbursements used in forecasting.') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="ghost" wire:click="runDueEntriesNow">
                {{ __('DEV: Run Due Entries Now (Use Cron In Production)') }}
            </flux:button>
            <flux:button variant="primary" wire:click="startCreating">{{ __('Add Schedule') }}</flux:button>
        </div>
    </div>

    <x-action-message on="recurring-generated">
        {{ __('Recurring generation command executed.') }}
    </x-action-message>

    @if ($showForm)
        <flux:card class="space-y-4">
            <flux:heading>{{ __('Add Schedule') }}</flux:heading>

            <form wire:submit="saveRecurring" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model.live="form.entry_type" :label="__('Type')" required>
                        <flux:select.option value="expense">{{ __('Expense') }}</flux:select.option>
                        <flux:select.option value="income">{{ __('Reimbursement') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="form.account_id" :label="__('Account')" required>
                        @foreach ($this->accounts as $account)
                            <flux:select.option :value="$account->id">{{ $account->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="form.car_id" :label="__('Car (Optional)')">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.amount" :label="__('Amount')" type="number" min="0.01" step="0.01" required />
                    <flux:select wire:model="form.cadence" :label="__('Cadence')" required>
                        <flux:select.option value="monthly">{{ __('Monthly') }}</flux:select.option>
                        <flux:select.option value="quarterly">{{ __('Quarterly') }}</flux:select.option>
                        <flux:select.option value="yearly">{{ __('Yearly') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="form.next_entry_date" :label="__('Next Entry Date')" type="date" required />
                    <flux:input wire:model="form.end_date" :label="__('End Date (Optional)')" type="date" />
                    <flux:input wire:model="form.reference" :label="__('Reference')" type="text" />
                </div>

                <flux:input wire:model="form.notes" :label="__('Notes')" type="text" />
                <flux:checkbox wire:model="form.is_active" :label="__('Active')" />

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">{{ __('Save Schedule') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                    <x-action-message on="recurring-saved">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>
        </flux:card>
    @endif

    @if ($this->recurringTransactions->isEmpty())
        <flux:card>
            <flux:text>{{ __('No recurring schedules yet.') }}</flux:text>
        </flux:card>
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full min-w-[920px] text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Next Date') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Account') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Notes') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Cadence') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Amount') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->recurringTransactions as $schedule)
                        <tr
                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                            wire:click="openRecurringDetails({{ $schedule->id }})"
                        >
                            <td class="px-3 py-2">{{ $schedule->next_entry_date->format('d-m-Y') }}</td>
                            <td class="px-3 py-2">{{ $schedule->account?->name ?? __('N/A') }}</td>
                            <td class="px-3 py-2">
                                <div class="max-w-56 truncate">{{ $schedule->notes ?: __('N/A') }}</div>
                            </td>
                            <td class="px-3 py-2">{{ ucfirst($schedule->cadence) }}</td>
                            <td class="px-3 py-2 text-right">{{ $this->formatCurrency($schedule->amount) }}</td>
                            <td class="px-3 py-2">{{ $schedule->is_active ? __('Active') : __('Paused') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($showDetailsModal && $editingRecurringId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4">
            <div class="w-full max-w-2xl rounded-xl border border-zinc-300 bg-white p-5 shadow-2xl ring-1 ring-black/10 dark:border-zinc-600 dark:bg-zinc-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading>{{ __('Recurring Schedule') }}</flux:heading>
                        <flux:subheading>{{ __('Review and manage this schedule.') }}</flux:subheading>
                    </div>
                    <flux:button variant="ghost" wire:click="closeDetailsModal">{{ __('Close') }}</flux:button>
                </div>

                <div class="mt-4 space-y-4">
                    @if ($isModalEditing)
                        <div class="grid gap-4 md:grid-cols-2">
                            <flux:select wire:model.live="form.entry_type" :label="__('Type')" required>
                                <flux:select.option value="expense">{{ __('Expense') }}</flux:select.option>
                                <flux:select.option value="income">{{ __('Reimbursement') }}</flux:select.option>
                            </flux:select>

                            <flux:select wire:model="form.account_id" :label="__('Account')" required>
                                @foreach ($this->accounts as $account)
                                    <flux:select.option :value="$account->id">{{ $account->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="form.car_id" :label="__('Car (Optional)')">
                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                @foreach ($this->cars as $car)
                                    <flux:select.option :value="$car->id">
                                        {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="form.amount" :label="__('Amount')" type="number" min="0.01" step="0.01" required />
                            <flux:select wire:model="form.cadence" :label="__('Cadence')" required>
                                <flux:select.option value="monthly">{{ __('Monthly') }}</flux:select.option>
                                <flux:select.option value="quarterly">{{ __('Quarterly') }}</flux:select.option>
                                <flux:select.option value="yearly">{{ __('Yearly') }}</flux:select.option>
                            </flux:select>
                            <flux:input wire:model="form.next_entry_date" :label="__('Next Entry Date')" type="date" required />
                            <flux:input wire:model="form.end_date" :label="__('End Date (Optional)')" type="date" />
                            <flux:input wire:model="form.reference" :label="__('Reference')" type="text" />
                        </div>

                        <flux:input wire:model="form.notes" :label="__('Notes')" type="text" />
                        <flux:checkbox wire:model="form.is_active" :label="__('Active')" />
                    @else
                        <div class="grid gap-2 text-sm md:grid-cols-2">
                            <flux:text><strong>{{ __('Type') }}:</strong> {{ $form['entry_type'] === 'income' ? __('Reimbursement') : __('Expense') }}</flux:text>
                            <flux:text><strong>{{ __('Account') }}:</strong> {{ $this->accounts->firstWhere('id', (int) $form['account_id'])?->name ?? __('N/A') }}</flux:text>
                            <flux:text><strong>{{ __('Amount') }}:</strong> {{ $this->formatCurrency($form['amount']) }}</flux:text>
                            <flux:text><strong>{{ __('Cadence') }}:</strong> {{ ucfirst((string) $form['cadence']) }}</flux:text>
                            <flux:text><strong>{{ __('Next Entry Date') }}:</strong> {{ \Carbon\Carbon::parse((string) $form['next_entry_date'])->format('d-m-Y') }}</flux:text>
                            <flux:text><strong>{{ __('End Date') }}:</strong> {{ $form['end_date'] !== '' ? \Carbon\Carbon::parse((string) $form['end_date'])->format('d-m-Y') : __('None') }}</flux:text>
                            <flux:text><strong>{{ __('Reference') }}:</strong> {{ $form['reference'] !== '' ? $form['reference'] : __('N/A') }}</flux:text>
                            <flux:text><strong>{{ __('Status') }}:</strong> {{ $form['is_active'] ? __('Active') : __('Paused') }}</flux:text>
                        </div>
                        <flux:text><strong>{{ __('Notes') }}:</strong> {{ $form['notes'] !== '' ? $form['notes'] : __('N/A') }}</flux:text>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <div class="flex w-full items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            @if (! $isModalEditing)
                                <flux:button variant="primary" wire:click="startModalEdit">{{ __('Edit') }}</flux:button>
                            @else
                                <flux:button variant="primary" wire:click="saveRecurring">{{ __('Save') }}</flux:button>
                            @endif

                            <flux:button variant="ghost" wire:click="toggleRecurringActive({{ $editingRecurringId }})">
                                {{ $form['is_active'] ? __('Pause') : __('Activate') }}
                            </flux:button>
                        </div>

                        <div class="flex items-center gap-2">
                            @if (! $confirmingDelete)
                                <flux:button variant="danger" wire:click="confirmDeleteInModal">{{ __('Delete') }}</flux:button>
                            @else
                                <flux:text class="text-red-600 dark:text-red-400">{{ __('Confirm delete this schedule?') }}</flux:text>
                                <flux:button variant="danger" wire:click="deleteFromModal">{{ __('Confirm Delete') }}</flux:button>
                                <flux:button variant="ghost" wire:click="cancelDeleteInModal">{{ __('Cancel') }}</flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</section>
