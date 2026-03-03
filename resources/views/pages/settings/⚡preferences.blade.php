<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public string $preferred_currency = 'GBP';
    public string $measurement_system = 'imperial';
    public string $volume_unit = 'gallons';

    /**
     * @var array<string, string>
     */
    public array $currencyOptions = [
        'GBP' => 'GBP',
        'USD' => 'USD',
        'EUR' => 'EUR',
        'CAD' => 'CAD',
        'AUD' => 'AUD',
    ];

    /**
     * @var array<string, string>
     */
    public array $volumeUnitOptions = [
        'gallons' => 'Imperial Gallons',
        'liters' => 'Litres',
    ];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();

        $this->preferred_currency = $user->preferred_currency ?: 'GBP';
        $this->measurement_system = $user->measurement_system ?: 'imperial';
        $this->volume_unit = $user->volume_unit ?: ($this->measurement_system === 'metric' ? 'liters' : 'gallons');
    }

    /**
     * Update the car tracking preferences.
     */
    public function updatePreferences(): void
    {
        $validated = $this->validate([
            'preferred_currency' => ['required', Rule::in(array_keys($this->currencyOptions))],
            'measurement_system' => ['required', Rule::in(['imperial', 'metric'])],
            'volume_unit' => ['required', Rule::in(array_keys($this->volumeUnitOptions))],
        ]);

        Auth::user()->update($validated);

        $this->dispatch('preferences-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Preference Settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Preferences')" :subheading="__('Set your currency and distance/fuel measurement system')">
        <form wire:submit="updatePreferences" class="mt-6 space-y-6">
            <flux:select wire:model="preferred_currency" :label="__('Currency')" required>
                @foreach ($currencyOptions as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:radio.group wire:model="measurement_system" :label="__('Measurement system')">
                <flux:radio value="imperial" :label="__('MPH / Imperial Gallons')" />
                <flux:radio value="metric" :label="__('KM / Litres')" />
            </flux:radio.group>

            <flux:select wire:model="volume_unit" :label="__('Volume unit')">
                @foreach ($volumeUnitOptions as $value => $label)
                    <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>

                <x-action-message class="me-3" on="preferences-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
