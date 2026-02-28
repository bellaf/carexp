<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public string $ui_theme = 'classic';

    /**
     * @var array<string, string>
     */
    public array $themeOptions = [
        'classic' => 'Classic',
        'warm-paper' => 'Warm Paper',
        'soft-automotive' => 'Soft Automotive',
        'editorial-neutral' => 'Editorial Neutral',
    ];

    /**
     * @var array<string, string>
     */
    public array $themeDescriptions = [
        'classic' => 'Current neutral app palette.',
        'warm-paper' => 'Warm off-white surfaces with softer blue-grey actions.',
        'soft-automotive' => 'Cool mist greys with a restrained technical feel.',
        'editorial-neutral' => 'Greige surfaces with softer contrast and muted green accents.',
    ];

    public function mount(): void
    {
        $this->ui_theme = Auth::user()->ui_theme ?: 'classic';
    }

    public function updateTheme(): void
    {
        $validated = $this->validate([
            'ui_theme' => ['required', Rule::in(array_keys($this->themeOptions))],
        ]);

        Auth::user()->update($validated);

        $this->dispatch('appearance-updated');
        $this->dispatch('app-theme-updated', theme: $this->ui_theme);
    }
}; ?>

<section class="w-full" x-data x-on:app-theme-updated.window="document.body.dataset.uiTheme = $event.detail.theme">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Appearance Settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div class="space-y-8">
            <div class="space-y-3">
                <flux:heading size="lg">{{ __('Mode') }}</flux:heading>
                <flux:text>{{ __('Choose light, dark, or follow your system setting.') }}</flux:text>

                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
                </flux:radio.group>
            </div>

            <form wire:submit="updateTheme" class="space-y-6">
                <div class="space-y-3">
                    <flux:heading size="lg">{{ __('Theme') }}</flux:heading>
                    <flux:text>{{ __('Select a softer visual palette for light mode.') }}</flux:text>
                </div>

                <flux:radio.group wire:model="ui_theme" class="space-y-3">
                    @foreach ($themeOptions as $value => $label)
                        <label class="block cursor-pointer rounded-xl border border-zinc-200 bg-white px-4 py-3 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/70">
                            <div class="flex items-start gap-3">
                                <input type="radio" value="{{ $value }}" wire:model="ui_theme" class="mt-1">
                                <div class="space-y-1">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ __($label) }}</div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ __($themeDescriptions[$value]) }}</div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </flux:radio.group>

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>

                    <x-action-message class="me-3" on="appearance-updated">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>
        </div>
    </x-pages::settings.layout>
</section>
