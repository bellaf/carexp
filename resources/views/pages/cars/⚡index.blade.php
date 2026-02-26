<?php

use App\Models\Car;
use App\Support\CurrencyFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public ?int $editingCarId = null;

    /**
     * @var array<string, string>
     */
    public array $fuelTypeOptions = [
        'gasoline' => 'Petrol',
        'diesel' => 'Diesel',
        'hybrid' => 'Hybrid',
        'electric' => 'Electric',
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
        $this->editingCarId = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function editCar(int $carId): void
    {
        $car = Auth::user()->cars()->findOrFail($carId);

        $this->editingCarId = $car->id;
        $this->form = [
            'nickname' => $car->nickname ?? '',
            'year' => $car->year ?? '',
            'make' => $car->make,
            'model' => $car->model,
            'trim' => $car->trim ?? '',
            'vin' => $car->vin ?? '',
            'plate' => $car->plate ?? '',
            'fuel_type' => $car->fuel_type ?? '',
            'purchase_date' => $car->purchase_date?->format('Y-m-d') ?? '',
            'purchase_price' => $car->purchase_price !== null ? (string) $car->purchase_price : '',
            'purchase_odometer' => $car->purchase_odometer ?? '',
            'current_odometer' => $car->current_odometer ?? '',
        ];

        $this->showForm = true;
    }

    public function saveCar(): void
    {
        $form = $this->validate($this->carRules(), $this->carMessages())['form'];
        $attributes = $this->normalizeCarAttributes($form);

        if ($this->editingCarId !== null) {
            $car = Auth::user()->cars()->findOrFail($this->editingCarId);
            $car->update($attributes);
        } else {
            $car = Auth::user()->cars()->create($attributes);

            $hasDefaultCar = Auth::user()->cars()->where('is_default', true)->exists();

            if (! $hasDefaultCar) {
                $this->setDefaultCar($car->id);
            }
        }

        $this->cancelForm();
        $this->dispatch('car-saved');
    }

    public function archiveCar(int $carId): void
    {
        DB::transaction(function () use ($carId): void {
            $car = Auth::user()->cars()->findOrFail($carId);
            $wasDefault = (bool) $car->is_default;

            $car->update([
                'is_archived' => true,
                'is_default' => false,
            ]);

            if ($wasDefault) {
                $fallbackCar = Auth::user()->cars()
                    ->where('is_archived', false)
                    ->orderByDesc('updated_at')
                    ->first();

                if ($fallbackCar !== null) {
                    $this->setDefaultCar($fallbackCar->id);
                }
            }
        });
    }

    public function restoreCar(int $carId): void
    {
        $car = Auth::user()->cars()->findOrFail($carId);
        $car->update(['is_archived' => false]);

        $hasDefaultCar = Auth::user()->cars()->where('is_archived', false)->where('is_default', true)->exists();

        if (! $hasDefaultCar) {
            $this->setDefaultCar($car->id);
        }
    }

    public function setDefaultCar(int $carId): void
    {
        DB::transaction(function () use ($carId): void {
            $car = Auth::user()->cars()->where('is_archived', false)->findOrFail($carId);

            Auth::user()->cars()->update(['is_default' => false]);
            $car->update(['is_default' => true]);
        });
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingCarId = null;
        $this->resetForm();
    }

    public function fuelTypeLabel(?string $fuelType): string
    {
        if ($fuelType === null || $fuelType === '') {
            return 'Not set';
        }

        return $this->fuelTypeOptions[$fuelType] ?? ucfirst($fuelType);
    }

    public function formatCurrency(float|int|string|null $amount): string
    {
        return CurrencyFormatter::format($amount, Auth::user()->preferred_currency);
    }

    #[Computed]
    public function cars(): Collection
    {
        return Auth::user()
            ->cars()
            ->orderBy('is_archived')
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function carRules(): array
    {
        return [
            'form.nickname' => ['nullable', 'string', 'max:255'],
            'form.year' => ['nullable', 'integer', 'min:1886', 'max:'.((int) date('Y') + 1)],
            'form.make' => ['required', 'string', 'max:255'],
            'form.model' => ['required', 'string', 'max:255'],
            'form.trim' => ['nullable', 'string', 'max:255'],
            'form.vin' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('cars', 'vin')->ignore($this->editingCarId),
            ],
            'form.plate' => ['nullable', 'string', 'max:32'],
            'form.fuel_type' => ['nullable', Rule::in(array_keys($this->fuelTypeOptions))],
            'form.purchase_date' => ['nullable', 'date'],
            'form.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'form.purchase_odometer' => ['nullable', 'integer', 'min:0'],
            'form.current_odometer' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function carMessages(): array
    {
        return [
            'form.make.required' => 'Car make is required.',
            'form.model.required' => 'Car model is required.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function normalizeCarAttributes(array $form): array
    {
        foreach (['nickname', 'year', 'trim', 'vin', 'plate', 'fuel_type', 'purchase_date', 'purchase_price', 'purchase_odometer', 'current_odometer'] as $field) {
            if ($form[$field] === '') {
                $form[$field] = null;
            }
        }

        return $form;
    }

    protected function resetForm(): void
    {
        $this->form = [
            'nickname' => '',
            'year' => '',
            'make' => '',
            'model' => '',
            'trim' => '',
            'vin' => '',
            'plate' => '',
            'fuel_type' => '',
            'purchase_date' => '',
            'purchase_price' => '',
            'purchase_odometer' => '',
            'current_odometer' => '',
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Cars') }}</flux:heading>
            <flux:subheading>{{ __('Track the vehicles tied to your expenses.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" wire:click="startCreating">{{ __('Add Car') }}</flux:button>
    </div>

    @if ($showForm)
        <flux:card class="space-y-5">
            <flux:heading>{{ $editingCarId ? __('Edit Car') : __('Add Car') }}</flux:heading>

            <form wire:submit="saveCar" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="form.nickname" :label="__('Nickname')" type="text" />
                    <flux:input wire:model="form.year" :label="__('Year')" type="number" min="1886" />
                    <flux:input wire:model="form.make" :label="__('Make')" type="text" required />
                    <flux:input wire:model="form.model" :label="__('Model')" type="text" required />
                    <flux:input wire:model="form.trim" :label="__('Trim')" type="text" />
                    <flux:select wire:model="form.fuel_type" :label="__('Fuel Type')">
                        <flux:select.option value="">{{ __('Select fuel type') }}</flux:select.option>
                        @foreach ($fuelTypeOptions as $value => $label)
                            <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="form.vin" :label="__('VIN')" type="text" />
                    <flux:input wire:model="form.plate" :label="__('License Plate')" type="text" />
                    <flux:input wire:model="form.purchase_date" :label="__('Purchase Date')" type="date" />
                    <flux:input wire:model="form.purchase_price" :label="__('Purchase Price')" type="number" min="0" step="0.01" />
                    <flux:input wire:model="form.purchase_odometer" :label="__('Purchase Odometer')" type="number" min="0" step="1" />
                    <flux:input wire:model="form.current_odometer" :label="__('Current Odometer')" type="number" min="0" step="1" />
                </div>

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">{{ __('Save Car') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Cancel') }}</flux:button>

                    <x-action-message on="car-saved">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>
        </flux:card>
    @endif

    @if ($this->cars->isEmpty())
        <flux:card>
            <flux:text>{{ __('No cars added yet. Add your first car to start tracking expenses.') }}</flux:text>
        </flux:card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($this->cars as $car)
                <flux:card class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading>
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:heading>
                            <flux:subheading>
                                {{ $car->nickname ?: __('No nickname') }}
                            </flux:subheading>
                        </div>

                        @if ($car->is_archived)
                            <flux:badge>{{ __('Archived') }}</flux:badge>
                        @elseif ($car->is_default)
                            <flux:badge color="green">{{ __('Current Car') }}</flux:badge>
                        @endif
                    </div>

                    <div class="grid gap-2 text-sm">
                        <flux:text>{{ __('Fuel Type') }}: {{ __($this->fuelTypeLabel($car->fuel_type)) }}</flux:text>
                        <flux:text>{{ __('Current Odometer') }}: {{ $car->current_odometer ?? __('Not set') }}</flux:text>
                        <flux:text>{{ __('Purchase Price') }}: {{ $car->purchase_price !== null ? $this->formatCurrency($car->purchase_price) : __('Not set') }}</flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        @if (! $car->is_archived && ! $car->is_default)
                            <flux:button variant="ghost" wire:click="setDefaultCar({{ $car->id }})">{{ __('Set Current') }}</flux:button>
                        @endif

                        <flux:button variant="ghost" wire:click="editCar({{ $car->id }})">{{ __('Edit') }}</flux:button>

                        @if ($car->is_archived)
                            <flux:button variant="ghost" wire:click="restoreCar({{ $car->id }})">{{ __('Restore') }}</flux:button>
                        @else
                            <flux:button variant="danger" wire:click="archiveCar({{ $car->id }})">{{ __('Archive') }}</flux:button>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</section>
