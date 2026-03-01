<?php

use App\Models\MileageLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public bool $confirmingDelete = false;
    public ?int $editingMileageLogId = null;

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
        $this->editingMileageLogId = null;
        $this->resetForm();

        if ($this->cars->isNotEmpty()) {
            $carId = (string) $this->cars->first()->id;

            $this->form['car_id'] = $carId;
            $this->form['start_odometer'] = $this->defaultStartOdometerForCar((int) $carId);
        }

        $this->showForm = true;
    }

    public function editMileageLog(int $mileageLogId): void
    {
        $mileageLog = Auth::user()->mileageLogs()->findOrFail($mileageLogId);

        $this->editingMileageLogId = $mileageLog->id;
        $this->form = [
            'car_id' => (string) $mileageLog->car_id,
            'log_date' => $mileageLog->log_date->format('Y-m-d'),
            'start_odometer' => (string) $mileageLog->start_odometer,
            'end_odometer' => (string) $mileageLog->end_odometer,
            'locations' => $mileageLog->locations ?? '',
        ];

        $this->showForm = true;
        $this->confirmingDelete = false;
    }

    public function updatedFormCarId(string $carId): void
    {
        if ($this->editingMileageLogId !== null || $carId === '') {
            return;
        }

        $this->form['start_odometer'] = $this->defaultStartOdometerForCar((int) $carId);
    }

    public function saveMileageLog(): void
    {
        $attributes = $this->validate($this->rules(), $this->messages())['form'];
        $normalized = $this->normalizeAttributes($attributes);

        if ($this->editingMileageLogId !== null) {
            Auth::user()->mileageLogs()->findOrFail($this->editingMileageLogId)->update($normalized);
        } else {
            Auth::user()->mileageLogs()->create($normalized);
        }

        $this->cancelForm();
        $this->dispatch('mileage-log-saved');
    }

    public function deleteMileageLog(int $mileageLogId): void
    {
        Auth::user()->mileageLogs()->findOrFail($mileageLogId)->delete();
    }

    public function confirmDeleteEditing(): void
    {
        if ($this->editingMileageLogId === null) {
            return;
        }

        $this->confirmingDelete = true;
    }

    public function cancelDeleteEditing(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteEditingMileageLog(): void
    {
        if ($this->editingMileageLogId === null) {
            return;
        }

        $this->deleteMileageLog($this->editingMileageLogId);
        $this->cancelForm();
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->confirmingDelete = false;
        $this->editingMileageLogId = null;
        $this->resetForm();
    }

    #[Computed]
    public function cars(): Collection
    {
        return Auth::user()->cars()->where('is_archived', false)->orderByDesc('is_default')->orderBy('make')->orderBy('model')->get();
    }

    #[Computed]
    public function mileageLogs(): Collection
    {
        return Auth::user()->mileageLogs()
            ->with('car')
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.car_id' => ['required', 'integer', Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id()))],
            'form.log_date' => ['required', 'date'],
            'form.start_odometer' => ['required', 'integer', 'min:0'],
            'form.end_odometer' => ['required', 'integer', 'min:0', 'gte:form.start_odometer'],
            'form.locations' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'form.car_id.required' => 'Car is required.',
            'form.log_date.required' => 'Date is required.',
            'form.start_odometer.required' => 'Start odometer is required.',
            'form.end_odometer.required' => 'End odometer is required.',
            'form.end_odometer.gte' => 'End odometer must be greater than or equal to the start odometer.',
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function normalizeAttributes(array $attributes): array
    {
        return [
            'car_id' => (int) $attributes['car_id'],
            'log_date' => $attributes['log_date'],
            'start_odometer' => (int) $attributes['start_odometer'],
            'end_odometer' => (int) $attributes['end_odometer'],
            'locations' => filled($attributes['locations']) ? trim((string) $attributes['locations']) : null,
        ];
    }

    protected function resetForm(): void
    {
        $this->form = [
            'car_id' => '',
            'log_date' => now()->toDateString(),
            'start_odometer' => '',
            'end_odometer' => '',
            'locations' => '',
        ];
    }

    protected function defaultStartOdometerForCar(int $carId): string
    {
        $latestEndOdometer = Auth::user()->mileageLogs()
            ->where('car_id', $carId)
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->value('end_odometer');

        if ($latestEndOdometer !== null) {
            return (string) $latestEndOdometer;
        }

        $carCurrentOdometer = Auth::user()->cars()->whereKey($carId)->value('current_odometer');

        return $carCurrentOdometer !== null ? (string) $carCurrentOdometer : '';
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Mileage Logs') }}</flux:heading>
            <flux:subheading>{{ __('Record daily business mileage with odometer start and end readings.') }}</flux:subheading>
        </div>

        <flux:button class="w-full sm:w-auto" variant="primary" wire:click="startCreating">{{ __('Add Mileage Log') }}</flux:button>
    </div>

    <flux:modal :closable="false" wire:model="showForm" class="max-h-[90vh] overflow-y-auto border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[42rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ $editingMileageLogId ? __('Edit Mileage Log') : __('Add Mileage Log') }}</flux:heading>
                    <flux:subheading>{{ __('Save the date, odometer readings, and locations visited.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveMileageLog" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model.live="form.car_id" :label="__('Car')" required>
                        <flux:select.option value="">{{ __('Select car') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.log_date" :label="__('Date')" type="date" required />
                    <flux:input wire:model="form.start_odometer" :label="__('Start Odometer')" type="number" min="0" step="1" required />
                    <flux:input wire:model="form.end_odometer" :label="__('End Odometer')" type="number" min="0" step="1" required />
                </div>

                <flux:input wire:model="form.locations" :label="__('Locations (comma separated)')" type="text" />

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="primary">{{ __('Save Mileage Log') }}</flux:button>
                        <x-action-message on="mileage-log-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>

                    @if ($editingMileageLogId !== null)
                        @if ($confirmingDelete)
                            <div class="flex items-center gap-2">
                                <flux:text>{{ __('Delete this mileage log?') }}</flux:text>
                                <flux:button type="button" variant="danger" wire:click="deleteEditingMileageLog">{{ __('Confirm') }}</flux:button>
                                <flux:button type="button" variant="ghost" wire:click="cancelDeleteEditing">{{ __('Cancel') }}</flux:button>
                            </div>
                        @else
                            <flux:button type="button" variant="danger" wire:click="confirmDeleteEditing">{{ __('Delete') }}</flux:button>
                        @endif
                    @endif
                </div>
            </form>
        </div>
    </flux:modal>

    @if ($this->mileageLogs->isEmpty())
        <flux:card>
            <flux:text>{{ __('No mileage logs recorded yet.') }}</flux:text>
        </flux:card>
    @else
        <flux:card class="space-y-2">
            <flux:text>{{ __('Tap any mileage log to edit it.') }}</flux:text>
        </flux:card>

        <div class="space-y-3 md:hidden">
            @foreach ($this->mileageLogs as $mileageLog)
                <button
                    type="button"
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                    wire:click="editMileageLog({{ $mileageLog->id }})"
                    wire:key="mileage-log-card-{{ $mileageLog->id }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $mileageLog->log_date->format('d-m-Y') }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ trim(collect([$mileageLog->car?->year, $mileageLog->car?->make, $mileageLog->car?->model])->filter()->implode(' ')) }}</div>
                        </div>
                        <div class="text-right text-sm">
                            <div class="font-semibold">{{ number_format($mileageLog->end_odometer - $mileageLog->start_odometer) }} {{ __('miles') }}</div>
                            <div class="text-zinc-500 dark:text-zinc-400">{{ number_format($mileageLog->start_odometer) }}-{{ number_format($mileageLog->end_odometer) }}</div>
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ $mileageLog->locations ?: __('No locations recorded') }}
                    </div>
                </button>
            @endforeach
        </div>

        <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Date') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Car') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Start') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('End') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Miles') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Locations') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->mileageLogs as $mileageLog)
                        <tr
                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                            wire:click="editMileageLog({{ $mileageLog->id }})"
                        >
                            <td class="px-3 py-2">{{ $mileageLog->log_date->format('d-m-Y') }}</td>
                            <td class="px-3 py-2">{{ trim(collect([$mileageLog->car?->year, $mileageLog->car?->make, $mileageLog->car?->model])->filter()->implode(' ')) }}</td>
                            <td class="px-3 py-2">{{ number_format($mileageLog->start_odometer) }}</td>
                            <td class="px-3 py-2">{{ number_format($mileageLog->end_odometer) }}</td>
                            <td class="px-3 py-2">{{ number_format($mileageLog->end_odometer - $mileageLog->start_odometer) }}</td>
                            <td class="px-3 py-2">{{ $mileageLog->locations ?: __('N/A') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
