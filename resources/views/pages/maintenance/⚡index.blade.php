<?php

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\MaintenanceRecord;
use App\Support\CurrencyFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public ?int $editingMaintenanceId = null;

    /**
     * @var array<string, string>
     */
    public array $serviceTypeOptions = [
        'oil_change' => 'Oil Change',
        'tire_rotation' => 'Tire Rotation',
        'brake_service' => 'Brake Service',
        'battery_service' => 'Battery Service',
        'air_filter' => 'Air Filter',
        'inspection' => 'Inspection',
        'other' => 'Other',
    ];

    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function startCreating(): void
    {
        $this->editingMaintenanceId = null;
        $this->resetForm();

        if ($this->cars->isNotEmpty()) {
            $this->form['car_id'] = (string) $this->cars->first()->id;
        }

        $this->showForm = true;
    }

    public function editRecord(int $recordId): void
    {
        $record = Auth::user()->maintenanceRecords()->with('ledgerEntry')->findOrFail($recordId);

        $this->editingMaintenanceId = $record->id;
        $this->form = [
            'car_id' => (string) $record->car_id,
            'service_type_option' => array_key_exists($record->service_type, $this->serviceTypeOptions) ? $record->service_type : 'other',
            'service_type_custom' => array_key_exists($record->service_type, $this->serviceTypeOptions) ? '' : $record->service_type,
            'provider' => $record->provider ?? '',
            'service_date' => $record->service_date->format('Y-m-d'),
            'odometer' => $record->odometer ?? '',
            'cost' => $record->ledgerEntry !== null ? (string) $record->ledgerEntry->amount : '',
            'notes' => $record->notes ?? '',
            'next_due_date' => $record->next_due_date?->format('Y-m-d') ?? '',
            'next_due_odometer' => $record->next_due_odometer ?? '',
        ];

        $this->showForm = true;
    }

    public function saveRecord(): void
    {
        $form = $this->validate($this->maintenanceRules(), $this->maintenanceMessages())['form'];

        if ($form['service_type_option'] === 'other' && trim((string) ($form['service_type_custom'] ?? '')) === '') {
            $this->addError('form.service_type_custom', 'Custom service type is required.');

            return;
        }

        $normalized = $this->normalizeMaintenanceAttributes($form);
        $attributes = $normalized['attributes'];
        $amount = $normalized['amount'];

        DB::transaction(function () use ($attributes, $amount): void {
            if ($this->editingMaintenanceId !== null) {
                $record = Auth::user()->maintenanceRecords()->findOrFail($this->editingMaintenanceId);
                $record->update($attributes);
            } else {
                $record = Auth::user()->maintenanceRecords()->create($attributes);
            }

            $this->syncMaintenanceLedgerEntry($record, $amount);
        });

        $this->cancelForm();
        $this->dispatch('maintenance-saved');
    }

    public function deleteRecord(int $recordId): void
    {
        DB::transaction(function () use ($recordId): void {
            $record = Auth::user()->maintenanceRecords()->findOrFail($recordId);

            if ($record->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($record->ledger_entry_id)->delete();
            }

            $record->delete();
        });
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingMaintenanceId = null;
        $this->resetForm();
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
    public function maintenanceRecords(): Collection
    {
        return Auth::user()->maintenanceRecords()
            ->with(['car', 'ledgerEntry'])
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function reminderStats(): array
    {
        $overdue = 0;
        $dueSoon = 0;

        foreach ($this->maintenanceRecords as $record) {
            $status = $this->recordStatus($record);

            if ($status === 'overdue') {
                $overdue++;
            } elseif ($status === 'due_soon') {
                $dueSoon++;
            }
        }

        return [
            'overdue' => $overdue,
            'due_soon' => $dueSoon,
        ];
    }

    public function recordStatus(MaintenanceRecord $record): string
    {
        $currentOdometer = $record->car?->current_odometer;

        $isDateOverdue = $record->next_due_date !== null && $record->next_due_date->isPast();
        $isDateSoon = $record->next_due_date !== null && $record->next_due_date->isFuture() && $record->next_due_date->lte(now()->addDays(14));

        $isOdometerOverdue = $record->next_due_odometer !== null
            && $currentOdometer !== null
            && $currentOdometer >= $record->next_due_odometer;

        $isOdometerSoon = $record->next_due_odometer !== null
            && $currentOdometer !== null
            && $currentOdometer < $record->next_due_odometer
            && $currentOdometer >= ($record->next_due_odometer - 500);

        if ($isDateOverdue || $isOdometerOverdue) {
            return 'overdue';
        }

        if ($isDateSoon || $isOdometerSoon) {
            return 'due_soon';
        }

        return 'upcoming';
    }

    /**
     * @return array<string, mixed>
     */
    protected function maintenanceRules(): array
    {
        return [
            'form.car_id' => [
                'required',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'form.service_type_option' => ['required', Rule::in(array_keys($this->serviceTypeOptions))],
            'form.service_type_custom' => ['nullable', 'string', 'max:255'],
            'form.provider' => ['nullable', 'string', 'max:255'],
            'form.service_date' => ['required', 'date'],
            'form.odometer' => ['nullable', 'integer', 'min:0'],
            'form.cost' => ['nullable', 'numeric', 'min:0'],
            'form.notes' => ['nullable', 'string'],
            'form.next_due_date' => ['nullable', 'date'],
            'form.next_due_odometer' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function maintenanceMessages(): array
    {
        return [
            'form.car_id.required' => 'Please select a car.',
            'form.service_type.required' => 'Service type is required.',
            'form.service_date.required' => 'Service date is required.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function normalizeMaintenanceAttributes(array $form): array
    {
        foreach (['provider', 'service_type_custom', 'odometer', 'cost', 'notes', 'next_due_date', 'next_due_odometer'] as $field) {
            if ($form[$field] === '') {
                $form[$field] = null;
            }
        }

        $serviceType = $form['service_type_option'] === 'other'
            ? (string) ($form['service_type_custom'] ?? '')
            : (string) $form['service_type_option'];

        if ($serviceType === '') {
            $serviceType = 'other';
        }

        $amount = $form['cost'] !== null ? (float) $form['cost'] : null;

        return [
            'attributes' => [
                'car_id' => (int) $form['car_id'],
                'service_type' => $serviceType,
                'provider' => $form['provider'],
                'service_date' => $form['service_date'],
                'odometer' => $form['odometer'] !== null ? (int) $form['odometer'] : null,
                'notes' => $form['notes'],
                'next_due_date' => $form['next_due_date'],
                'next_due_odometer' => $form['next_due_odometer'] !== null ? (int) $form['next_due_odometer'] : null,
            ],
            'amount' => $amount,
        ];
    }

    protected function syncMaintenanceLedgerEntry(MaintenanceRecord $record, ?float $amount): void
    {
        if ($amount === null || $amount <= 0) {
            if ($record->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($record->ledger_entry_id)->delete();
                $record->update(['ledger_entry_id' => null]);
            }

            return;
        }

        $account = Account::query()->firstOrCreate(
            ['key' => 'maintenance_expense'],
            [
                'user_id' => null,
                'name' => 'Maintenance',
                'group' => 'expense',
                'is_system' => true,
                'is_active' => true,
            ],
        );

        $entryAttributes = [
            'user_id' => Auth::id(),
            'car_id' => $record->car_id,
            'account_id' => $account->id,
            'entry_date' => $record->service_date->format('Y-m-d'),
            'entry_type' => 'expense',
            'amount' => $amount,
            'source_type' => 'maintenance_record',
            'source_id' => $record->id,
            'reference' => $record->provider,
            'notes' => $record->notes,
        ];

        $entry = $record->ledger_entry_id !== null
            ? Auth::user()->ledgerEntries()->findOrFail($record->ledger_entry_id)
            : new LedgerEntry();

        $entry->fill($entryAttributes);
        $entry->save();

        $updates = [];

        if ($record->ledger_entry_id !== $entry->id) {
            $updates['ledger_entry_id'] = $entry->id;
        }

        if ($updates !== []) {
            $record->update($updates);
        }
    }

    protected function resetForm(): void
    {
        $this->form = [
            'car_id' => '',
            'service_type_option' => 'oil_change',
            'service_type_custom' => '',
            'provider' => '',
            'service_date' => now()->format('Y-m-d'),
            'odometer' => '',
            'cost' => '',
            'notes' => '',
            'next_due_date' => '',
            'next_due_odometer' => '',
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Maintenance') }}</flux:heading>
            <flux:subheading>{{ __('Log servicing and track upcoming due reminders.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" wire:click="startCreating" :disabled="$this->cars->isEmpty()">
            {{ __('Add Record') }}
        </flux:button>
    </div>

    @if ($this->cars->isEmpty())
        <flux:card>
            <flux:text>{{ __('Add a car first before creating maintenance records.') }}</flux:text>
        </flux:card>
    @endif

    <flux:card class="space-y-2">
        <flux:text>{{ __('Overdue') }}: <strong>{{ $this->reminderStats['overdue'] }}</strong></flux:text>
        <flux:text>{{ __('Due Soon') }}: <strong>{{ $this->reminderStats['due_soon'] }}</strong></flux:text>
    </flux:card>

    <flux:modal :closable="false" wire:model="showForm" class="border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[48rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div>
                <flux:heading>{{ $editingMaintenanceId ? __('Edit Maintenance') : __('Add Maintenance') }}</flux:heading>
                <flux:subheading>{{ __('Add service details and optional due reminders.') }}</flux:subheading>
            </div>

            <form wire:submit="saveRecord" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="form.car_id" :label="__('Car')" required>
                        <flux:select.option value="">{{ __('Select car') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="form.service_type_option" :label="__('Service Type')" required>
                        @foreach ($serviceTypeOptions as $value => $label)
                            <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if (($form['service_type_option'] ?? null) === 'other')
                        <flux:input wire:model="form.service_type_custom" :label="__('Custom Service Type')" type="text" required />
                    @endif

                    <flux:input wire:model="form.provider" :label="__('Provider/Shop')" type="text" />
                    <flux:input wire:model="form.service_date" :label="__('Service Date')" type="date" required />
                    <flux:input wire:model="form.odometer" :label="__('Odometer')" type="number" min="0" step="1" />
                    <flux:input wire:model="form.cost" :label="__('Cost')" type="number" min="0" step="0.01" />
                    <flux:input wire:model="form.next_due_date" :label="__('Next Due Date')" type="date" />
                    <flux:input wire:model="form.next_due_odometer" :label="__('Next Due Odometer')" type="number" min="0" step="1" />
                </div>

                <flux:input wire:model="form.notes" :label="__('Notes')" type="text" />

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">{{ __('Save Record') }}</flux:button>
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <x-action-message on="maintenance-saved">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>
        </div>
    </flux:modal>

    @if ($this->maintenanceRecords->isEmpty())
        <flux:card>
            <flux:text>{{ __('No maintenance records found for the current filter.') }}</flux:text>
        </flux:card>
    @else
        <div class="space-y-3">
            @foreach ($this->maintenanceRecords as $record)
                @php($status = $this->recordStatus($record))

                <flux:card class="space-y-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading>{{ $record->service_type }}</flux:heading>
                            <flux:subheading>
                                {{ $record->service_date->format('d-m-Y') }}
                                ·
                                {{ trim(collect([$record->car->year, $record->car->make, $record->car->model])->filter()->implode(' ')) }}
                            </flux:subheading>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($status === 'overdue')
                                <flux:badge color="red">{{ __('Overdue') }}</flux:badge>
                            @elseif ($status === 'due_soon')
                                <flux:badge color="yellow">{{ __('Due Soon') }}</flux:badge>
                            @else
                                <flux:badge>{{ __('Upcoming') }}</flux:badge>
                            @endif

                            <flux:button variant="ghost" wire:click="editRecord({{ $record->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button variant="danger" wire:click="deleteRecord({{ $record->id }})">{{ __('Delete') }}</flux:button>
                        </div>
                    </div>

                    <div class="grid gap-2 text-sm md:grid-cols-3">
                        <flux:text>{{ __('Provider') }}: {{ $record->provider ?: __('N/A') }}</flux:text>
                        <flux:text>{{ __('Cost') }}: {{ $record->ledgerEntry !== null ? $this->formatCurrency($record->ledgerEntry->amount) : __('N/A') }}</flux:text>
                        <flux:text>{{ __('Odometer') }}: {{ $record->odometer ?? __('N/A') }}</flux:text>
                        <flux:text>{{ __('Next Due Date') }}: {{ $record->next_due_date?->format('d-m-Y') ?: __('N/A') }}</flux:text>
                        <flux:text>{{ __('Next Due Odometer') }}: {{ $record->next_due_odometer ?? __('N/A') }}</flux:text>
                    </div>

                    @if ($record->notes)
                        <flux:text>{{ $record->notes }}</flux:text>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</section>
