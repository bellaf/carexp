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

        if ($this->categories->isNotEmpty()) {
            $this->form['expense_category_id'] = (string) $this->categories->first()->id;
        }

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
            'expense_category_id' => (string) $quickAction->expense_category_id,
            'car_id' => $quickAction->car_id !== null ? (string) $quickAction->car_id : '',
            'amount' => (string) $quickAction->amount,
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
    public function categories(): Collection
    {
        return ExpenseCategory::query()->orderBy('name')->get();
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

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'form.car_id' => ['nullable', 'integer', Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id()))],
            'form.amount' => ['nullable', 'numeric', 'min:0'],
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
            'form.expense_category_id.required' => 'Category is required.',
            'form.amount.min' => 'Amount cannot be negative.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function normalizeAttributes(array $form): array
    {
        foreach (['car_id', 'vendor', 'notes'] as $field) {
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
            'expense_category_id' => (int) $form['expense_category_id'],
            'car_id' => $form['car_id'] !== null ? (int) $form['car_id'] : null,
            'amount' => (float) ($form['amount'] ?: 0),
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
            'expense_category_id' => '',
            'car_id' => '',
            'amount' => '',
            'vendor' => '',
            'notes' => '',
            'tags' => '',
            'is_active' => true,
            'sort_order' => '0',
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Quick Actions') }}</flux:heading>
            <flux:subheading>{{ __('Define one-click expense actions for common costs.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" wire:click="startCreating">{{ __('Add Quick Action') }}</flux:button>
    </div>

    <flux:modal :closable="false" wire:model="showForm" class="border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[48rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ $editingQuickActionId ? __('Edit Quick Action') : __('Add Quick Action') }}</flux:heading>
                    <flux:subheading>{{ __('Set category, amount, and optional defaults used when the dashboard button is pressed.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveQuickAction" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="form.name" :label="__('Action Name')" type="text" required />

                    <flux:select wire:model="form.expense_category_id" :label="__('Category')" required>
                        <flux:select.option value="">{{ __('Select category') }}</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="form.car_id" :label="__('Car (Optional)')">
                        <flux:select.option value="">{{ __('Use default car') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.amount" :label="__('Amount (Optional)')" type="number" min="0" step="0.01" />
                    <flux:input wire:model="form.vendor" :label="__('Vendor (Optional)')" type="text" />
                    <flux:input wire:model="form.sort_order" :label="__('Sort Order')" type="number" min="0" step="1" />
                </div>

                <flux:input wire:model="form.tags" :label="__('Tags (comma separated)')" type="text" />
                <flux:input wire:model="form.notes" :label="__('Notes (Optional)')" type="text" />
                <flux:checkbox wire:model="form.is_active" :label="__('Active')" />

                <div class="flex items-center justify-between gap-3">
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
        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Name') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Category') }}</th>
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
                            <td class="px-3 py-2">{{ $quickAction->expenseCategory?->name ?? __('N/A') }}</td>
                            <td class="px-3 py-2">{{ $quickAction->car ? trim(collect([$quickAction->car->year, $quickAction->car->make, $quickAction->car->model])->filter()->implode(' ')) : __('Default') }}</td>
                            <td class="px-3 py-2 text-right">{{ $this->formatCurrency($quickAction->amount) }}</td>
                            <td class="px-3 py-2">{{ $quickAction->is_active ? __('Active') : __('Hidden') }}</td>
                            <td class="px-3 py-2">{{ $quickAction->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
