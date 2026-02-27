<?php

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Reimbursement;
use App\Support\CurrencyFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public bool $confirmingDelete = false;
    public ?int $editingReimbursementId = null;

    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    public ?string $filterAccountId = null;
    public string $filterPeriod = 'this_month';

    public function mount(): void
    {
        $this->ensureDefaultIncomeAccounts();
        $this->resetForm();
    }

    public function startCreating(): void
    {
        $this->editingReimbursementId = null;
        $this->resetForm();

        if ($this->cars->isNotEmpty()) {
            $this->form['car_id'] = (string) $this->cars->first()->id;
        }

        $this->form['account_id'] = (string) $this->defaultIncomeAccount()->id;
        $this->showForm = true;
    }

    public function editReimbursement(int $reimbursementId): void
    {
        $reimbursement = Auth::user()->reimbursements()->with('ledgerEntry')->findOrFail($reimbursementId);

        $this->editingReimbursementId = $reimbursement->id;
        $this->form = [
            'car_id' => (string) $reimbursement->car_id,
            'account_id' => (string) ($reimbursement->ledgerEntry?->account_id ?? $this->defaultIncomeAccount()->id),
            'reimbursed_date' => $reimbursement->reimbursed_date->format('Y-m-d'),
            'amount' => $reimbursement->ledgerEntry !== null ? (string) $reimbursement->ledgerEntry->amount : '',
            'notes' => $reimbursement->notes ?? '',
        ];

        $this->showForm = true;
        $this->confirmingDelete = false;
    }

    public function saveReimbursement(): void
    {
        $form = $this->validate($this->reimbursementRules(), $this->reimbursementMessages())['form'];
        $normalized = $this->normalizeAttributes($form);
        $attributes = $normalized['attributes'];
        $accountId = $normalized['account_id'];
        $amount = $normalized['amount'];

        DB::transaction(function () use ($attributes, $accountId, $amount): void {
            if ($this->editingReimbursementId !== null) {
                $reimbursement = Auth::user()->reimbursements()->findOrFail($this->editingReimbursementId);
                $reimbursement->update($attributes);
            } else {
                $reimbursement = Auth::user()->reimbursements()->create($attributes);
            }

            $this->syncLedgerEntry($reimbursement, $accountId, $amount);
        });

        $this->cancelForm();
        $this->dispatch('reimbursement-saved');
    }

    public function deleteReimbursement(int $reimbursementId): void
    {
        DB::transaction(function () use ($reimbursementId): void {
            $reimbursement = Auth::user()->reimbursements()->findOrFail($reimbursementId);

            if ($reimbursement->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($reimbursement->ledger_entry_id)->delete();
            }

            $reimbursement->delete();
        });
    }

    public function clearFilters(): void
    {
        $this->filterAccountId = null;
        $this->filterPeriod = 'this_month';
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingReimbursementId = null;
        $this->confirmingDelete = false;
        $this->resetForm();
    }

    public function confirmDeleteEditing(): void
    {
        if ($this->editingReimbursementId === null) {
            return;
        }

        $this->confirmingDelete = true;
    }

    public function cancelDeleteEditing(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteEditingReimbursement(): void
    {
        if ($this->editingReimbursementId === null) {
            return;
        }

        $this->deleteReimbursement($this->editingReimbursementId);
        $this->cancelForm();
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
    public function incomeAccounts(): Collection
    {
        return Account::query()
            ->where('group', 'income')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereIn('key', [
                    'company_car_allowance_income',
                    'company_business_fuel_tolls_income',
                ])->orWhere('is_system', false);
            })
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function reimbursements(): Collection
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

        return Auth::user()->reimbursements()
            ->with(['car', 'ledgerEntry.account'])
            ->when($this->filterAccountId, fn ($query) => $query->whereHas('ledgerEntry', fn ($ledgerQuery) => $ledgerQuery->where('account_id', (int) $this->filterAccountId)))
            ->when($periodStartDate !== null, fn ($query) => $query->whereDate('reimbursed_date', '>=', $periodStartDate))
            ->when($periodEndDate !== null, fn ($query) => $query->whereDate('reimbursed_date', '<=', $periodEndDate))
            ->orderByDesc('reimbursed_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->reimbursements
            ->map(fn (Reimbursement $reimbursement): float => (float) ($reimbursement->ledgerEntry?->amount ?? 0))
            ->sum();
    }

    /**
     * @return array<string, mixed>
     */
    protected function reimbursementRules(): array
    {
        return [
            'form.car_id' => [
                'required',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'form.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')],
            'form.reimbursed_date' => ['required', 'date'],
            'form.amount' => ['required', 'numeric', 'min:0.01'],
            'form.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function reimbursementMessages(): array
    {
        return [
            'form.car_id.required' => 'Please select a car.',
            'form.account_id.required' => 'Please select reimbursement type.',
            'form.reimbursed_date.required' => 'Reimbursement date is required.',
            'form.amount.required' => 'Amount is required.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function normalizeAttributes(array $form): array
    {
        if ($form['notes'] === '') {
            $form['notes'] = null;
        }

        return [
            'attributes' => [
                'car_id' => (int) $form['car_id'],
                'reimbursed_date' => $form['reimbursed_date'],
                'source' => null,
                'reference' => null,
                'notes' => $form['notes'],
            ],
            'account_id' => (int) $form['account_id'],
            'amount' => (float) $form['amount'],
        ];
    }

    protected function ensureDefaultIncomeAccounts(): void
    {
        foreach ([
            'company_car_allowance_income' => 'Company Car Allowance',
            'company_business_fuel_tolls_income' => 'Company Business Fuel & Tolls Reimbursement',
        ] as $key => $name) {
            Account::query()->updateOrCreate(
                ['key' => $key],
                [
                    'user_id' => null,
                    'name' => $name,
                    'group' => 'income',
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }

    protected function defaultIncomeAccount(): Account
    {
        return Account::query()->firstOrCreate(
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

    protected function syncLedgerEntry(Reimbursement $reimbursement, int $accountId, float $amount): void
    {
        $entryAttributes = [
            'user_id' => Auth::id(),
            'car_id' => $reimbursement->car_id,
            'account_id' => $accountId,
            'entry_date' => $reimbursement->reimbursed_date->format('Y-m-d'),
            'entry_type' => 'income',
            'amount' => $amount,
            'source_type' => 'reimbursement',
            'source_id' => $reimbursement->id,
            'reference' => null,
            'notes' => $reimbursement->notes,
        ];

        $entry = $reimbursement->ledger_entry_id !== null
            ? Auth::user()->ledgerEntries()->findOrFail($reimbursement->ledger_entry_id)
            : new LedgerEntry();

        $entry->fill($entryAttributes);
        $entry->save();

        if ($reimbursement->ledger_entry_id !== $entry->id) {
            $reimbursement->update(['ledger_entry_id' => $entry->id]);
        }
    }

    protected function resetForm(): void
    {
        $this->form = [
            'car_id' => '',
            'account_id' => '',
            'reimbursed_date' => now()->format('Y-m-d'),
            'amount' => '',
            'notes' => '',
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Reimbursements') }}</flux:heading>
            <flux:subheading>{{ __('Track company allowance and business fuel/tolls repayments.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" wire:click="startCreating" :disabled="$this->cars->isEmpty()">
            {{ __('Add Reimbursement') }}
        </flux:button>
    </div>

    @if ($this->cars->isEmpty())
        <flux:card>
            <flux:text>{{ __('Add a car first before creating reimbursements.') }}</flux:text>
        </flux:card>
    @endif

    <flux:modal :closable="false" wire:model="showForm" class="border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[48rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ $editingReimbursementId ? __('Edit Reimbursement') : __('Add Reimbursement') }}</flux:heading>
                    <flux:subheading>{{ __('Choose reimbursement type, date, and amount.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveReimbursement" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="form.car_id" :label="__('Car')" required>
                        <flux:select.option value="">{{ __('Select car') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="form.account_id" :label="__('Reimbursement Type')" required>
                        <flux:select.option value="">{{ __('Select reimbursement type') }}</flux:select.option>
                        @foreach ($this->incomeAccounts as $account)
                            <flux:select.option :value="$account->id">{{ $account->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.reimbursed_date" :label="__('Reimbursed Date')" type="date" required />
                    <flux:input wire:model="form.amount" :label="__('Amount')" type="number" min="0.01" step="0.01" required />
                </div>

                <flux:input wire:model="form.notes" :label="__('Notes (optional)')" type="text" />

                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="primary">{{ __('Save Reimbursement') }}</flux:button>
                        <x-action-message on="reimbursement-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($editingReimbursementId !== null && ! $confirmingDelete)
                            <flux:button type="button" variant="danger" wire:click="confirmDeleteEditing">{{ __('Delete') }}</flux:button>
                        @elseif ($editingReimbursementId !== null && $confirmingDelete)
                            <flux:text class="text-red-600 dark:text-red-400">{{ __('Confirm delete this reimbursement?') }}</flux:text>
                            <flux:button type="button" variant="danger" wire:click="deleteEditingReimbursement">{{ __('Confirm Delete') }}</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="cancelDeleteEditing">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-64">
                <flux:select wire:model.live="filterAccountId" :label="__('Filter Type')">
                    <flux:select.option value="">{{ __('All reimbursement types') }}</flux:select.option>
                    @foreach ($this->incomeAccounts as $account)
                        <flux:select.option :value="$account->id">{{ $account->name }}</flux:select.option>
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

        <flux:text>{{ __('Filtered reimbursements total') }}: <strong>{{ $this->formatCurrency($this->filteredTotal) }}</strong></flux:text>
    </flux:card>

    @if ($this->reimbursements->isEmpty())
        <flux:card>
            <flux:text>{{ __('No reimbursements found for the selected filters.') }}</flux:text>
        </flux:card>
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Date') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Type') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Notes') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->reimbursements as $reimbursement)
                        <tr
                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                            tabindex="0"
                            wire:click="editReimbursement({{ $reimbursement->id }})"
                            wire:key="reimbursement-row-{{ $reimbursement->id }}"
                        >
                            <td class="px-3 py-2">{{ $reimbursement->reimbursed_date->format('d-m-Y') }}</td>
                            <td class="px-3 py-2">{{ $reimbursement->ledgerEntry?->account?->name ?? __('N/A') }}</td>
                            <td class="px-3 py-2">
                                <div class="max-w-72 truncate">{{ $reimbursement->notes ?: __('N/A') }}</div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ $this->formatCurrency($reimbursement->ledgerEntry?->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
