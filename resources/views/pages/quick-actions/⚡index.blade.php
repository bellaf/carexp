<?php

use App\Models\ExpenseCategory;
use App\Support\CurrencyFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public ?int $editingQuickActionId = null;

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
        $this->editingQuickActionId = null;
        $this->resetForm();

        if ($this->cars->isNotEmpty()) {
            $this->form['car_id'] = (string) $this->cars->first()->id;
        }

        $this->showForm = true;
    }

    public function editQuickAction(int $quickActionId): void
    {
        $quickAction = Auth::user()->quickActions()->findOrFail($quickActionId);

        $this->editingQuickActionId = $quickAction->id;
        $this->form = [
            'name' => $quickAction->name,
            'entry_target' => $quickAction->entry_target,
            'expense_category_id' => (string) $quickAction->expense_category_id,
            'car_id' => $quickAction->car_id !== null ? (string) $quickAction->car_id : '',
            'amount' => (string) $quickAction->amount,
            'fuel_volume' => $quickAction->fuel_volume !== null ? (string) $quickAction->fuel_volume : '',
            'fuel_full_tank' => (bool) $quickAction->fuel_full_tank,
            'mileage_locations' => $quickAction->mileage_locations ?? '',
            'mileage_distance' => $quickAction->mileage_distance !== null ? (string) $quickAction->mileage_distance : '',
            'vendor' => $quickAction->vendor ?? '',
            'notes' => $quickAction->notes ?? '',
            'tags' => implode(', ', $quickAction->tags ?? []),
            'is_active' => (bool) $quickAction->is_active,
            'sort_order' => (string) $quickAction->sort_order,
        ];

        $this->showForm = true;
    }

    public function saveQuickAction(): void
    {
        $form = $this->validate($this->rules(), $this->messages())['form'];
        $attributes = $this->normalizeAttributes($form);

        if ($this->editingQuickActionId !== null) {
            Auth::user()->quickActions()->findOrFail($this->editingQuickActionId)->update($attributes);
        } else {
            Auth::user()->quickActions()->create($attributes);
        }

        $this->cancelForm();
        $this->dispatch('quick-action-saved');
    }

    public function deleteQuickAction(int $quickActionId): void
    {
        Auth::user()->quickActions()->findOrFail($quickActionId)->delete();

        if ($this->editingQuickActionId === $quickActionId) {
            $this->cancelForm();
        }
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingQuickActionId = null;
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
    public function quickActions(): Collection
    {
        return Auth::user()->quickActions()
            ->with(['car', 'expenseCategory'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        return ExpenseCategory::query()->orderBy('name')->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.entry_target' => ['required', Rule::in(['expense', 'fuel_log', 'mileage_log'])],
            'form.expense_category_id' => [
                Rule::requiredIf((string) ($this->form['entry_target'] ?? '') === 'expense'),
                'nullable',
                'integer',
                Rule::exists('expense_categories', 'id'),
            ],
            'form.car_id' => ['nullable', 'integer', Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id()))],
            'form.amount' => ['nullable', 'numeric', 'min:0'],
            'form.fuel_volume' => ['nullable', 'numeric', 'min:0.001'],
            'form.fuel_full_tank' => ['boolean'],
            'form.mileage_locations' => ['nullable', 'string', 'max:255'],
            'form.mileage_distance' => [Rule::requiredIf((string) ($this->form['entry_target'] ?? '') === 'mileage_log'), 'nullable', 'integer', 'min:1', 'max:9999'],
            'form.vendor' => ['nullable', 'string', 'max:255'],
            'form.notes' => ['nullable', 'string'],
            'form.tags' => ['nullable', 'string', 'max:255'],
            'form.is_active' => ['boolean'],
            'form.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'form.name.required' => 'Action name is required.',
            'form.entry_target.required' => 'Target is required.',
            'form.expense_category_id.required' => 'Expense category is required for expense quick actions.',
            'form.amount.min' => 'Amount cannot be negative.',
            'form.fuel_volume.min' => 'Fuel volume must be greater than zero when provided.',
            'form.mileage_distance.required' => 'Standard miles are required for mileage quick actions.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function normalizeAttributes(array $form): array
    {
        foreach (['car_id', 'vendor', 'notes', 'mileage_locations', 'mileage_distance'] as $field) {
            if ($form[$field] === '') {
                $form[$field] = null;
            }
        }

        $tags = collect(explode(',', (string) $form['tags']))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $form['name'],
            'entry_target' => $form['entry_target'],
            'expense_category_id' => $this->resolveExpenseCategoryId($form),
            'car_id' => $form['car_id'] !== null ? (int) $form['car_id'] : null,
            'amount' => (float) ($form['amount'] ?: 0),
            'fuel_volume' => $form['fuel_volume'] !== '' && $form['fuel_volume'] !== null ? (float) $form['fuel_volume'] : null,
            'fuel_full_tank' => (bool) $form['fuel_full_tank'],
            'mileage_locations' => $form['mileage_locations'],
            'mileage_distance' => $form['mileage_distance'] !== null ? (int) $form['mileage_distance'] : null,
            'vendor' => $form['vendor'],
            'notes' => $form['notes'],
            'tags' => $tags !== [] ? $tags : null,
            'is_active' => (bool) $form['is_active'],
            'sort_order' => (int) ($form['sort_order'] ?: 0),
        ];
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'entry_target' => 'expense',
            'expense_category_id' => (string) $this->defaultExpenseCategoryId(),
            'car_id' => '',
            'amount' => '',
            'fuel_volume' => '',
            'fuel_full_tank' => true,
            'mileage_locations' => '',
            'mileage_distance' => '',
            'vendor' => '',
            'notes' => '',
            'tags' => '',
            'is_active' => true,
            'sort_order' => '0',
        ];
    }

    protected function resolveExpenseCategoryId(array $form): int
    {
        $entryTarget = (string) $form['entry_target'];

        if ($entryTarget === 'expense' && filled($form['expense_category_id'] ?? null)) {
            return (int) $form['expense_category_id'];
        }

        $category = $entryTarget === 'fuel_log'
            ? $this->fuelCategory()
            : $this->otherCategory();

        return (int) $category->id;
    }

    protected function defaultExpenseCategoryId(): int
    {
        return (int) $this->otherCategory()->id;
    }

    protected function fuelCategory(): ExpenseCategory
    {
        return ExpenseCategory::query()->firstOrCreate(
            ['key' => 'fuel'],
            ['name' => 'Fuel', 'is_system' => true],
        );
    }

    protected function otherCategory(): ExpenseCategory
    {
        return ExpenseCategory::query()->firstOrCreate(
            ['key' => 'other'],
            ['name' => 'Other', 'is_system' => true],
        );
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Quick Actions') }}</flux:heading>
            <flux:subheading>{{ __('Define one-click expense actions for common costs.') }}</flux:subheading>
        </div>

        <flux:button class="w-full sm:w-auto" variant="primary" wire:click="startCreating">{{ __('Add Quick Action') }}</flux:button>
    </div>

    <flux:modal :closable="false" wire:model="showForm" class="max-h-[90vh] overflow-y-auto border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[48rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ $editingQuickActionId ? __('Edit Quick Action') : __('Add Quick Action') }}</flux:heading>
                    <flux:subheading>{{ __('Set target, amount, and optional defaults used when the dashboard button is pressed.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveQuickAction" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="form.name" :label="__('Action Name')" type="text" required />

                    <flux:select wire:model.live="form.entry_target" :label="__('Target')" required>
                        <flux:select.option value="expense">{{ __('Expense Entry') }}</flux:select.option>
                        <flux:select.option value="fuel_log">{{ __('Fuel Log Entry') }}</flux:select.option>
                        <flux:select.option value="mileage_log">{{ __('Mileage Log Entry') }}</flux:select.option>
                    </flux:select>

                    <div x-data x-show="$wire.form.entry_target === 'expense'" x-cloak>
                        <flux:select wire:model="form.expense_category_id" :label="__('Expense Category')" required>
                            @foreach ($this->categories as $category)
                                <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:select wire:model="form.car_id" :label="__('Car (Optional)')">
                        <flux:select.option value="">{{ __('Use default car') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <div x-data x-show="['expense', 'fuel_log'].includes($wire.form.entry_target)" x-cloak>
                        <flux:input wire:model="form.amount" :label="__('Amount (Optional)')" type="number" min="0" step="0.01" />
                    </div>
                    <div x-data x-show="$wire.form.entry_target === 'expense'" x-cloak>
                        <flux:input wire:model="form.vendor" :label="__('Vendor (Optional)')" type="text" />
                    </div>
                    <flux:input wire:model="form.sort_order" :label="__('Sort Order')" type="number" min="0" step="1" />

                    <div x-data x-show="$wire.form.entry_target === 'fuel_log'" x-cloak>
                        <flux:input wire:model="form.fuel_volume" :label="__('Fuel Volume (Optional)')" type="number" min="0.001" step="0.001" />
                    </div>
                    <div x-data x-show="$wire.form.entry_target === 'fuel_log'" class="self-end" x-cloak>
                        <flux:checkbox wire:model="form.fuel_full_tank" :label="__('Full Tank')" />
                    </div>
                    <div x-data x-show="$wire.form.entry_target === 'mileage_log'" x-cloak>
                        <flux:input wire:model="form.mileage_distance" :label="__('Standard Miles')" type="number" min="1" step="1" />
                    </div>
                    <div x-data class="md:col-span-2" x-show="$wire.form.entry_target === 'mileage_log'" x-cloak>
                        <flux:input wire:model="form.mileage_locations" :label="__('Standard Locations (comma separated)')" type="text" />
                    </div>
                </div>

                <flux:input wire:model="form.tags" :label="__('Tags (comma separated)')" type="text" />
                <flux:input wire:model="form.notes" :label="__('Notes (Optional)')" type="text" />
                <flux:checkbox wire:model="form.is_active" :label="__('Active')" />

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="primary">{{ __('Save Quick Action') }}</flux:button>
                        <x-action-message on="quick-action-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>

                    @if ($editingQuickActionId !== null)
                        <flux:button
                            type="button"
                            variant="danger"
                            x-on:click="if (confirm('{{ __('Delete this quick action?') }}')) { $wire.deleteQuickAction({{ $editingQuickActionId }}); }"
                        >
                            {{ __('Delete') }}
                        </flux:button>
                    @endif
                </div>
            </form>
        </div>
    </flux:modal>

    @if ($this->quickActions->isEmpty())
        <flux:card>
            <flux:text>{{ __('No quick actions defined yet.') }}</flux:text>
        </flux:card>
    @else
        <flux:card class="space-y-2">
            <flux:text>{{ __('Tap any quick action to edit it.') }}</flux:text>
        </flux:card>

        <div class="space-y-3 md:hidden">
            @foreach ($this->quickActions as $quickAction)
                <button
                    type="button"
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                    wire:click="editQuickAction({{ $quickAction->id }})"
                    wire:key="quick-action-card-{{ $quickAction->id }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $quickAction->name }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ match ($quickAction->entry_target) {
                                'fuel_log' => __('Fuel Log'),
                                'mileage_log' => __('Mileage Log'),
                                default => __('Expense'),
                            } }}</div>
                        </div>
                        <div class="text-right text-sm">
                            <div class="font-semibold">{{ $quickAction->entry_target === 'mileage_log' ? number_format((int) ($quickAction->mileage_distance ?? 0)).' '.__('miles') : $this->formatCurrency($quickAction->amount) }}</div>
                            <div class="text-zinc-500 dark:text-zinc-400">{{ $quickAction->is_active ? __('Active') : __('Hidden') }}</div>
                        </div>
                    </div>
                    <dl class="mt-3 grid gap-2 text-sm">
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Car') }}</dt>
                            <dd>{{ $quickAction->car ? trim(collect([$quickAction->car->year, $quickAction->car->make, $quickAction->car->model])->filter()->implode(' ')) : __('Default') }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Order') }}</dt>
                            <dd>{{ $quickAction->sort_order }}</dd>
                        </div>
                    </dl>
                </button>
            @endforeach
        </div>
        <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
            <table class="w-full min-w-[980px] text-left text-sm tabular-nums">
                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Name') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Target') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Car') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Amount') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->quickActions as $quickAction)
                        <tr
                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                            wire:click="editQuickAction({{ $quickAction->id }})"
                        >
                            <td class="px-3 py-2">{{ $quickAction->name }}</td>
                            <td class="px-3 py-2">{{ match ($quickAction->entry_target) {
                                'fuel_log' => __('Fuel Log'),
                                'mileage_log' => __('Mileage Log'),
                                default => __('Expense'),
                            } }}</td>
                            <td class="px-3 py-2">{{ $quickAction->car ? trim(collect([$quickAction->car->year, $quickAction->car->make, $quickAction->car->model])->filter()->implode(' ')) : __('Default') }}</td>
                            <td class="px-3 py-2 text-right">{{ $quickAction->entry_target === 'mileage_log' ? number_format((int) ($quickAction->mileage_distance ?? 0)).' '.__('miles') : $this->formatCurrency($quickAction->amount) }}</td>
                            <td class="px-3 py-2">{{ $quickAction->is_active ? __('Active') : __('Hidden') }}</td>
                            <td class="px-3 py-2">{{ $quickAction->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
