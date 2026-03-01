<?php

use App\Models\Account;
use App\Models\LedgerEntry;
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
    public ?int $editingLedgerEntryId = null;

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
        $this->editingLedgerEntryId = null;
        $this->resetForm();

        if ($this->cars->isNotEmpty()) {
            $this->form['car_id'] = (string) $this->cars->first()->id;
        }

        $this->form['account_id'] = (string) $this->defaultIncomeAccount()->id;
        $this->showForm = true;
    }

    public function editReimbursement(int $ledgerEntryId): void
    {
        $ledgerEntry = Auth::user()->ledgerEntries()
            ->with(['account', 'car'])
            ->where('entry_type', 'income')
            ->findOrFail($ledgerEntryId);

        $this->editingLedgerEntryId = $ledgerEntry->id;
        $this->form = [
            'car_id' => (string) $ledgerEntry->car_id,
            'account_id' => (string) $ledgerEntry->account_id,
            'reimbursed_date' => $ledgerEntry->entry_date->format('Y-m-d'),
            'amount' => (string) $ledgerEntry->amount,
            'notes' => $ledgerEntry->notes ?? '',
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
            $ledgerEntry = $this->editingLedgerEntryId !== null
                ? Auth::user()->ledgerEntries()->findOrFail($this->editingLedgerEntryId)
                : new LedgerEntry();

            $this->syncLedgerEntry($ledgerEntry, $attributes, $accountId, $amount);
        });

        $this->cancelForm();
        $this->dispatch('reimbursement-saved');
    }

    public function deleteReimbursement(int $ledgerEntryId): void
    {
        DB::transaction(function () use ($ledgerEntryId): void {
            $ledgerEntry = Auth::user()->ledgerEntries()
                ->where('entry_type', 'income')
                ->findOrFail($ledgerEntryId);

            $ledgerEntry->delete();
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
        $this->editingLedgerEntryId = null;
        $this->confirmingDelete = false;
        $this->resetForm();
    }

    public function confirmDeleteEditing(): void
    {
        if ($this->editingLedgerEntryId === null) {
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
        if ($this->editingLedgerEntryId === null) {
            return;
        }

        $this->deleteReimbursement($this->editingLedgerEntryId);
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
                ])->orWhere(fn ($customQuery) => $customQuery
                    ->where('is_system', false)
                    ->where('user_id', Auth::id()));
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

        return Auth::user()->ledgerEntries()
            ->with(['car', 'account'])
            ->where('entry_type', 'income')
            ->when($this->filterAccountId, fn ($query) => $query->where('account_id', (int) $this->filterAccountId))
            ->when($periodStartDate !== null, fn ($query) => $query->whereDate('entry_date', '>=', $periodStartDate))
            ->when($periodEndDate !== null, fn ($query) => $query->whereDate('entry_date', '<=', $periodEndDate))
            ->whereHas('account', fn ($query) => $query->where('group', 'income'))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->reimbursements
            ->map(fn (LedgerEntry $ledgerEntry): float => (float) $ledgerEntry->amount)
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
            'form.account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('group', 'income')
                    ->where('is_active', true)
                    ->where(fn ($scopeQuery) => $scopeQuery
                        ->where('is_system', true)
                        ->orWhere(fn ($customQuery) => $customQuery
                            ->where('is_system', false)
                            ->where('user_id', Auth::id())))),
            ],
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
            Account::query()->firstOrCreate(
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

    /**
     * @param array{car_id:int,reimbursed_date:string,source:null,reference:null,notes:?string} $attributes
     */
    protected function syncLedgerEntry(LedgerEntry $ledgerEntry, array $attributes, int $accountId, float $amount): void
    {
        $sourceType = $ledgerEntry->exists && $ledgerEntry->source_type !== null
            ? $ledgerEntry->source_type
            : 'reimbursement';

        $ledgerEntry->fill([
            'user_id' => Auth::id(),
            'car_id' => $attributes['car_id'],
            'account_id' => $accountId,
            'entry_date' => $attributes['reimbursed_date'],
            'entry_type' => 'income',
            'amount' => $amount,
            'source_type' => $sourceType,
            'source_id' => $sourceType === 'reimbursement' ? null : $ledgerEntry->source_id,
            'reference' => null,
            'notes' => $attributes['notes'],
        ]);

        $ledgerEntry->save();
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
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Reimbursements') }}</flux:heading>
            <flux:subheading>{{ __('Track company allowance and business fuel/tolls repayments.') }}</flux:subheading>
        </div>

        <flux:button class="w-full sm:w-auto" variant="primary" wire:click="startCreating" :disabled="$this->cars->isEmpty()">
            {{ __('Add Reimbursement') }}
        </flux:button>
    </div>

    @if ($this->cars->isEmpty())
        <flux:card>
            <flux:text>{{ __('Add a car first before creating reimbursements.') }}</flux:text>
        </flux:card>
    @endif

    <flux:modal :closable="false" wire:model="showForm" class="max-h-[90vh] overflow-y-auto border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[48rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ $editingLedgerEntryId ? __('Edit Reimbursement') : __('Add Reimbursement') }}</flux:heading>
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

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="primary">{{ __('Save Reimbursement') }}</flux:button>
                        <x-action-message on="reimbursement-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($editingLedgerEntryId !== null && ! $confirmingDelete)
                            <flux:button type="button" variant="danger" wire:click="confirmDeleteEditing">{{ __('Delete') }}</flux:button>
                        @elseif ($editingLedgerEntryId !== null && $confirmingDelete)
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
                    <flux:select wire:model.live="filterAccountId" :label="__('Filter Type')">
                        <flux:select.option value="">{{ __('All reimbursement types') }}</flux:select.option>
                        @foreach ($this->incomeAccounts as $account)
                            <flux:select.option :value="$account->id">{{ $account->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </details>
        </div>

        <div class="hidden flex-wrap items-end gap-3 md:flex">
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
        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Tap any reimbursement to edit it.') }}</flux:text>
    </flux:card>

    @if ($this->reimbursements->isEmpty())
        <flux:card>
            <flux:text>{{ __('No reimbursements found for the selected filters.') }}</flux:text>
        </flux:card>
    @else
        <div class="space-y-3 md:hidden">
            @foreach ($this->reimbursements as $reimbursement)
                <button
                    type="button"
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                    wire:click="editReimbursement({{ $reimbursement->id }})"
                    wire:key="reimbursement-card-{{ $reimbursement->id }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $reimbursement->account?->name ?? __('N/A') }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $reimbursement->entry_date->format('d-m-Y') }}</div>
                        </div>
                        <div class="text-right font-semibold text-emerald-700 dark:text-emerald-400">{{ $this->formatCurrency($reimbursement->amount) }}</div>
                    </div>
                    <dl class="mt-3 grid gap-2 text-sm">
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Notes') }}</dt>
                            <dd>{{ $reimbursement->notes ?: __('N/A') }}</dd>
                        </div>
                    </dl>
                </button>
            @endforeach
        </div>
        <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
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
                            <td class="px-3 py-2">{{ $reimbursement->entry_date->format('d-m-Y') }}</td>
                            <td class="px-3 py-2">{{ $reimbursement->account?->name ?? __('N/A') }}</td>
                            <td class="px-3 py-2">
                                <div class="max-w-72 truncate">{{ $reimbursement->notes ?: __('N/A') }}</div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ $this->formatCurrency($reimbursement->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
