<?php

use App\Models\Account;
use App\Models\FuelLog;
use App\Models\LedgerEntry;
use App\Support\CurrencyFormatter;
use App\Support\FuelEfficiencyCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public bool $confirmingDelete = false;
    public ?int $editingFuelLogId = null;

    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    public string $filterPeriod = 'this_month';

    public function mount(): void
    {
        $this->resetForm();
    }

    public function startCreating(): void
    {
        $this->editingFuelLogId = null;
        $this->resetForm();

        if ($this->cars->isNotEmpty()) {
            $this->form['car_id'] = (string) $this->cars->first()->id;
        }

        $this->showForm = true;
    }

    public function editFuelLog(int $fuelLogId): void
    {
        $fuelLog = Auth::user()->fuelLogs()->with('ledgerEntry')->findOrFail($fuelLogId);

        $this->editingFuelLogId = $fuelLog->id;
        $this->form = [
            'car_id' => (string) $fuelLog->car_id,
            'log_date' => $fuelLog->log_date->format('Y-m-d'),
            'odometer' => (string) $fuelLog->odometer,
            'volume' => (string) $fuelLog->volume,
            'volume_unit' => $fuelLog->volume_unit ?: $this->preferredVolumeUnit(),
            'total_cost' => $fuelLog->ledgerEntry !== null ? (string) $fuelLog->ledgerEntry->amount : '',
            'price_per_unit' => $fuelLog->price_per_unit !== null ? (string) $fuelLog->price_per_unit : '',
            'full_tank' => $fuelLog->full_tank,
        ];

        $this->showForm = true;
        $this->confirmingDelete = false;
    }

    public function saveFuelLog(): void
    {
        $form = $this->validate($this->fuelLogRules(), $this->fuelLogMessages())['form'];
        $normalized = $this->normalizeFuelLogAttributes($form);
        $attributes = $normalized['attributes'];
        $amount = $normalized['amount'];

        DB::transaction(function () use ($attributes, $amount): void {
            $previousCarId = null;

            if ($this->editingFuelLogId !== null) {
                $fuelLog = Auth::user()->fuelLogs()->findOrFail($this->editingFuelLogId);
                $previousCarId = (int) $fuelLog->car_id;
                $fuelLog->update($attributes);
            } else {
                $fuelLog = Auth::user()->fuelLogs()->create($attributes);
            }

            $this->syncFuelLedgerEntry($fuelLog, $amount);
            $this->syncCarCurrentOdometer((int) $fuelLog->car_id);
            $this->recalculateFuelEfficienciesForCar((int) $fuelLog->car_id);

            if ($previousCarId !== null && $previousCarId !== (int) $fuelLog->car_id) {
                $this->syncCarCurrentOdometer($previousCarId);
                $this->recalculateFuelEfficienciesForCar($previousCarId);
            }
        });

        $this->cancelForm();
        $this->dispatch('fuel-log-saved');
    }

    public function deleteFuelLog(int $fuelLogId): void
    {
        DB::transaction(function () use ($fuelLogId): void {
            $fuelLog = Auth::user()->fuelLogs()->findOrFail($fuelLogId);
            $carId = (int) $fuelLog->car_id;

            if ($fuelLog->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($fuelLog->ledger_entry_id)->delete();
            }

            $fuelLog->delete();
            $this->syncCarCurrentOdometer($carId);
            $this->recalculateFuelEfficienciesForCar($carId);
        });
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingFuelLogId = null;
        $this->confirmingDelete = false;
        $this->resetForm();
    }

    public function confirmDeleteEditing(): void
    {
        if ($this->editingFuelLogId === null) {
            return;
        }

        $this->confirmingDelete = true;
    }

    public function cancelDeleteEditing(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteEditingFuelLog(): void
    {
        if ($this->editingFuelLogId === null) {
            return;
        }

        $this->deleteFuelLog($this->editingFuelLogId);
        $this->cancelForm();
    }

    public function formatCurrency(float|int|string|null $amount): string
    {
        return CurrencyFormatter::format($amount, Auth::user()->preferred_currency);
    }

    public function currencySymbol(): string
    {
        return CurrencyFormatter::symbol(Auth::user()->preferred_currency);
    }

    public function preferredVolumeUnit(): string
    {
        $user = Auth::user();

        if (in_array($user->volume_unit, ['gallons', 'litres'], true)) {
            return $user->volume_unit;
        }

        return $user->measurement_system === 'metric' ? 'litres' : 'gallons';
    }

    public function volumeUnitLabel(?string $volumeUnit = null): string
    {
        $unit = $volumeUnit ?: $this->preferredVolumeUnit();

        return $unit === 'litres' ? 'L' : 'gal';
    }

    public function efficiencyLabel(): string
    {
        return Auth::user()->measurement_system === 'metric' ? 'KM/L' : 'MPG';
    }

    #[Computed]
    public function cars(): Collection
    {
        return Auth::user()->cars()->where('is_archived', false)->orderBy('make')->orderBy('model')->get();
    }

    #[Computed]
    public function fuelLogs(): Collection
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

        return Auth::user()->fuelLogs()
            ->with(['car', 'ledgerEntry'])
            ->when($periodStartDate !== null, fn ($query) => $query->whereDate('log_date', '>=', $periodStartDate))
            ->when($periodEndDate !== null, fn ($query) => $query->whereDate('log_date', '<=', $periodEndDate))
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function averageEfficiency(): ?float
    {
        return FuelEfficiencyCalculator::averageForLogs(
            $this->fuelLogs,
            (string) Auth::user()->measurement_system,
        );
    }

    #[Computed]
    public function totalFuelSpend(): float
    {
        return (float) $this->fuelLogs
            ->map(fn (FuelLog $fuelLog): float => (float) ($fuelLog->ledgerEntry?->amount ?? 0))
            ->sum();
    }

    /**
     * @return array<string, mixed>
     */
    protected function fuelLogRules(): array
    {
        return [
            'form.car_id' => [
                'required',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'form.log_date' => ['required', 'date'],
            'form.odometer' => ['required', 'integer', 'min:0'],
            'form.volume' => ['required', 'numeric', 'min:0.001'],
            'form.total_cost' => ['required', 'numeric', 'min:0.01'],
            'form.price_per_unit' => ['nullable', 'numeric', 'min:0.001'],
            'form.full_tank' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fuelLogMessages(): array
    {
        return [
            'form.car_id.required' => 'Please select a car.',
            'form.log_date.required' => 'Date is required.',
            'form.odometer.required' => 'Odometer is required.',
            'form.volume.required' => 'Volume is required.',
            'form.total_cost.required' => 'Total cost is required.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function normalizeFuelLogAttributes(array $form): array
    {
        $amount = (float) $form['total_cost'];
        $pricePerUnit = $form['price_per_unit'] !== '' && $form['price_per_unit'] !== null
            ? (float) $form['price_per_unit']
            : round($amount / (float) $form['volume'], 3);

        $volumeUnit = in_array($form['volume_unit'] ?? null, ['gallons', 'litres'], true)
            ? $form['volume_unit']
            : $this->preferredVolumeUnit();

        return [
            'attributes' => [
                'car_id' => (int) $form['car_id'],
                'log_date' => $form['log_date'],
                'odometer' => (int) $form['odometer'],
                'volume' => (float) $form['volume'],
                'volume_unit' => $volumeUnit,
                'price_per_unit' => $pricePerUnit,
                'full_tank' => (bool) $form['full_tank'],
                'calculated_efficiency' => null,
            ],
            'amount' => $amount,
        ];
    }

    protected function recalculateFuelEfficienciesForCar(int $carId): void
    {
        $fuelLogs = Auth::user()->fuelLogs()
            ->where('car_id', $carId)
            ->orderBy('odometer')
            ->orderBy('id')
            ->get();

        $previousLog = null;

        foreach ($fuelLogs as $fuelLog) {
            $efficiency = null;

            if ($fuelLog->full_tank && $previousLog !== null && $previousLog->full_tank) {
                $distance = (int) $fuelLog->odometer - (int) $previousLog->odometer;
                $volumeForEfficiency = $this->volumeForEfficiency((float) $fuelLog->volume, (string) $fuelLog->volume_unit);

                if ($distance > 0 && $volumeForEfficiency > 0) {
                    $efficiency = round($distance / $volumeForEfficiency, 3);
                }
            }

            $current = $fuelLog->calculated_efficiency !== null ? (float) $fuelLog->calculated_efficiency : null;

            if ($current !== $efficiency) {
                $fuelLog->update(['calculated_efficiency' => $efficiency]);
            }

            $previousLog = $fuelLog;
        }
    }

    protected function volumeForEfficiency(float $volume, string $volumeUnit): float
    {
        if (Auth::user()->measurement_system === 'metric') {
            return $volumeUnit === 'litres'
                ? $volume
                : ($volume * 4.54609);
        }

        return $volumeUnit === 'gallons'
            ? $volume
            : ($volume / 4.54609);
    }

    protected function syncFuelLedgerEntry(FuelLog $fuelLog, float $amount): void
    {
        $account = Account::query()->firstOrCreate(
            ['key' => 'fuel_expense'],
            [
                'user_id' => null,
                'name' => 'Fuel',
                'group' => 'expense',
                'is_system' => true,
                'is_active' => true,
            ],
        );

        $entryAttributes = [
            'user_id' => Auth::id(),
            'car_id' => $fuelLog->car_id,
            'account_id' => $account->id,
            'entry_date' => $fuelLog->log_date->format('Y-m-d'),
            'entry_type' => 'expense',
            'amount' => $amount,
            'source_type' => 'fuel_log',
            'source_id' => $fuelLog->id,
            'reference' => 'Fuel Log',
            'notes' => null,
        ];

        $entry = $fuelLog->ledger_entry_id !== null
            ? Auth::user()->ledgerEntries()->findOrFail($fuelLog->ledger_entry_id)
            : new LedgerEntry();

        $entry->fill($entryAttributes);
        $entry->save();

        $updates = [];

        if ($fuelLog->ledger_entry_id !== $entry->id) {
            $updates['ledger_entry_id'] = $entry->id;
        }

        if ($updates !== []) {
            $fuelLog->update($updates);
        }
    }

    protected function syncCarCurrentOdometer(int $carId): void
    {
        $latestOdometer = Auth::user()->fuelLogs()
            ->where('car_id', $carId)
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->value('odometer');

        Auth::user()->cars()
            ->whereKey($carId)
            ->update([
                'current_odometer' => $latestOdometer !== null ? (int) $latestOdometer : null,
            ]);
    }

    protected function resetForm(): void
    {
        $this->form = [
            'car_id' => '',
            'log_date' => now()->format('Y-m-d'),
            'odometer' => '',
            'volume' => '',
            'volume_unit' => $this->preferredVolumeUnit(),
            'total_cost' => '',
            'price_per_unit' => '',
            'full_tank' => true,
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Fuel Logs') }}</flux:heading>
            <flux:subheading>{{ __('Track fill-ups, fuel cost, and efficiency trends.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" class="w-full sm:w-auto" wire:click="startCreating" :disabled="$this->cars->isEmpty()">
            {{ __('Add Fuel Log') }}
        </flux:button>
    </div>

    @if ($this->cars->isEmpty())
        <flux:card>
            <flux:text>{{ __('Add a car first before creating fuel logs.') }}</flux:text>
        </flux:card>
    @endif

    <flux:modal :closable="false" wire:model="showForm" class="max-h-[90vh] overflow-y-auto border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[48rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                <flux:heading>{{ $editingFuelLogId ? __('Edit Fuel Log') : __('Add Fuel Log') }}</flux:heading>
                <flux:subheading>{{ __('Add or update fill-up details.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveFuelLog" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="form.car_id" :label="__('Car')" required>
                        <flux:select.option value="">{{ __('Select car') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.log_date" :label="__('Date')" type="date" required />
                    <flux:input wire:model="form.odometer" :label="__('Odometer')" type="number" min="0" step="1" required />
                    <flux:input wire:model="form.volume" :label="__('Volume') . ' (' . $this->volumeUnitLabel($form['volume_unit']) . ')'" type="number" min="0.001" step="0.001" required />
                    <flux:input wire:model="form.total_cost" :label="__('Total Cost')" type="number" min="0.01" step="0.01" required />
                    <flux:input wire:model="form.price_per_unit" :label="__('Price Per Unit (optional)') . ' (' . $this->currencySymbol() . '/' . $this->volumeUnitLabel($form['volume_unit']) . ')'" type="number" min="0.001" step="0.001" />
                </div>

                <flux:checkbox wire:model="form.full_tank" :label="__('Full tank fill-up')" />

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="primary">{{ __('Save Fuel Log') }}</flux:button>
                        <x-action-message on="fuel-log-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($editingFuelLogId !== null && ! $confirmingDelete)
                            <flux:button type="button" variant="danger" wire:click="confirmDeleteEditing">{{ __('Delete') }}</flux:button>
                        @elseif ($editingFuelLogId !== null && $confirmingDelete)
                            <flux:text class="text-red-600 dark:text-red-400">{{ __('Confirm delete this fuel log?') }}</flux:text>
                            <flux:button type="button" variant="danger" wire:click="deleteEditingFuelLog">{{ __('Confirm Delete') }}</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="cancelDeleteEditing">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:card class="space-y-4">
        <div class="w-full sm:w-56">
            <flux:select wire:model.live="filterPeriod" :label="__('Period')">
                <flux:select.option value="this_month">{{ __('This Month') }}</flux:select.option>
                <flux:select.option value="last_month">{{ __('Last Month') }}</flux:select.option>
                <flux:select.option value="year_to_date">{{ __('Year to Date') }}</flux:select.option>
                <flux:select.option value="all_time">{{ __('All Time') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <flux:text>{{ __('Fuel spend (filtered)') }}: <strong>{{ $this->formatCurrency($this->totalFuelSpend) }}</strong></flux:text>
            <flux:text>
                {{ __('Average efficiency') }} ({{ $this->efficiencyLabel() }}):
                <strong>{{ $this->averageEfficiency !== null ? number_format($this->averageEfficiency, 3) : __('N/A') }}</strong>
            </flux:text>
        </div>
        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Tap any fuel entry to edit it.') }}</flux:text>
    </flux:card>

    @if ($this->fuelLogs->isEmpty())
        <flux:card>
            <flux:text>{{ __('No fuel logs found for the current filter.') }}</flux:text>
        </flux:card>
    @else
        <div class="space-y-3 md:hidden">
            @foreach ($this->fuelLogs as $fuelLog)
                <button
                    type="button"
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                    wire:click="editFuelLog({{ $fuelLog->id }})"
                    wire:key="fuel-card-{{ $fuelLog->id }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $fuelLog->log_date->format('d-m-Y') }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Odometer') }}: {{ number_format((float) $fuelLog->odometer) }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold">{{ $this->formatCurrency($fuelLog->ledgerEntry?->amount) }}</div>
                            @if ($fuelLog->full_tank)
                                <span class="mt-2 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('Full') }}</span>
                            @else
                                <span class="mt-2 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Partial') }}</span>
                            @endif
                        </div>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Volume') }}</dt>
                            <dd>{{ number_format((float) $fuelLog->volume, 3) }} {{ $this->volumeUnitLabel($fuelLog->volume_unit) }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Price/Unit') }}</dt>
                            <dd>{{ $this->currencySymbol() }}{{ number_format((float) $fuelLog->price_per_unit, 3) }}/{{ $this->volumeUnitLabel($fuelLog->volume_unit) }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Efficiency (mpg)') }}</dt>
                            <dd>{{ $fuelLog->calculated_efficiency !== null ? number_format((float) $fuelLog->calculated_efficiency, 3) : __('N/A') }}</dd>
                        </div>
                    </dl>
                </button>
            @endforeach
        </div>
        <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
            <table class="w-full min-w-[860px] text-left text-sm tabular-nums">
                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Date') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Odometer') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Volume') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Fill') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Price/Unit') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Efficiency (mpg)') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->fuelLogs as $fuelLog)
                        <tr
                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                            tabindex="0"
                            wire:click="editFuelLog({{ $fuelLog->id }})"
                            wire:key="fuel-row-{{ $fuelLog->id }}"
                        >
                            <td class="px-3 py-2">{{ $fuelLog->log_date->format('d-m-Y') }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $fuelLog->odometer) }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $fuelLog->volume, 3) }} {{ $this->volumeUnitLabel($fuelLog->volume_unit) }}</td>
                            <td class="px-3 py-2">
                                @if ($fuelLog->full_tank)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('Full') }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">{{ __('Partial') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $this->currencySymbol() }}{{ number_format((float) $fuelLog->price_per_unit, 3) }}/{{ $this->volumeUnitLabel($fuelLog->volume_unit) }}</td>
                            <td class="px-3 py-2">
                                {{ $fuelLog->calculated_efficiency !== null ? number_format((float) $fuelLog->calculated_efficiency, 3) : __('N/A') }}
                            </td>
                            <td class="px-3 py-2 text-right">{{ $this->formatCurrency($fuelLog->ledgerEntry?->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
