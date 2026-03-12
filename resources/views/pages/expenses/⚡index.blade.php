<?php

use App\Concerns\FormatsAttachmentUploadErrors;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerEntry;
use App\Models\Car;
use App\Support\AttachmentManager;
use App\Support\CurrencyFormatter;
use App\Support\ExpenseAccountResolver;
use App\Support\LatestOdometerResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use FormatsAttachmentUploadErrors;
    use WithFileUploads;

    public bool $showForm = false;
    public bool $confirmingDelete = false;
    public ?int $editingExpenseId = null;
    public ?int $editingCategoryId = null;
    public string $categoryName = '';
    public array $newAttachments = [];

    /**
     * @var array<string, mixed>
     */
    public array $form = [];
    public ?string $filterCategoryId = null;
    public string $filterPeriod = 'this_month';

    public function mount(): void
    {
        $this->resetForm();
    }

    public function startCreating(): void
    {
        $this->editingExpenseId = null;
        $this->resetForm();

        if ($this->categories->isNotEmpty()) {
            $this->form['expense_category_id'] = (string) $this->categories->first()->id;
        }

        if ($this->cars->isNotEmpty()) {
            $carId = (int) $this->cars->first()->id;
            $this->form['car_id'] = (string) $carId;
            $this->form['odometer'] = $this->defaultOdometerForCar($carId);
        }

        $this->showForm = true;
    }

    public function updatedFormCarId(string $carId): void
    {
        if ($this->editingExpenseId !== null) {
            return;
        }

        if ($carId === '') {
            $this->form['odometer'] = '';

            return;
        }

        $this->form['odometer'] = $this->defaultOdometerForCar((int) $carId);
    }

    public function editExpense(int $expenseId): void
    {
        $expense = Auth::user()->expenses()->findOrFail($expenseId);

        $this->editingExpenseId = $expense->id;
        $this->form = [
            'car_id' => (string) $expense->car_id,
            'expense_category_id' => (string) $expense->expense_category_id,
            'amount' => (string) $expense->amount,
            'expense_date' => $expense->expense_date->format('Y-m-d'),
            'odometer' => $expense->odometer ?? '',
            'vendor' => $expense->vendor ?? '',
            'notes' => $expense->notes ?? '',
            'tags' => implode(', ', $expense->tags ?? []),
        ];

        $this->showForm = true;
        $this->confirmingDelete = false;
    }

    public function saveExpense(): void
    {
        $validated = $this->validate(
            array_merge($this->expenseRules(), $this->attachmentRules()),
            array_merge($this->expenseMessages(), $this->attachmentMessages()),
        );
        $form = $validated['form'];
        $attributes = $this->normalizeExpenseAttributes($form);

        DB::transaction(function () use ($attributes): void {
            if ($this->editingExpenseId !== null) {
                $expense = Auth::user()->expenses()->findOrFail($this->editingExpenseId);
                $expense->update($attributes);
            } else {
                $expense = Auth::user()->expenses()->create($attributes);
            }

            $this->syncLedgerEntry($expense);
            app(AttachmentManager::class)->storeMany($expense, $this->newAttachments);
        });

        $this->cancelForm();
        $this->dispatch('expense-saved');
    }

    public function deleteExpense(int $expenseId): void
    {
        DB::transaction(function () use ($expenseId): void {
            $expense = Auth::user()->expenses()->findOrFail($expenseId);

            if ($expense->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($expense->ledger_entry_id)->delete();
            }

            $expense->delete();
        });
    }

    public function clearFilters(): void
    {
        $this->filterCategoryId = null;
        $this->filterPeriod = 'this_month';
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingExpenseId = null;
        $this->confirmingDelete = false;
        $this->newAttachments = [];
        $this->resetForm();
    }

    public function confirmDeleteEditing(): void
    {
        if ($this->editingExpenseId === null) {
            return;
        }

        $this->confirmingDelete = true;
    }

    public function cancelDeleteEditing(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteEditingExpense(): void
    {
        if ($this->editingExpenseId === null) {
            return;
        }

        $this->deleteExpense($this->editingExpenseId);
        $this->cancelForm();
    }

    public function deleteAttachment(int $attachmentId): void
    {
        if ($this->editingExpenseId === null) {
            return;
        }

        $expense = Auth::user()->expenses()->with('attachments')->findOrFail($this->editingExpenseId);
        $attachment = $expense->attachments()->findOrFail($attachmentId);

        app(AttachmentManager::class)->delete($attachment);

        unset($this->editingAttachments);
    }

    public function startCreatingCategory(): void
    {
        $this->resetErrorBag('categoryName');
        $this->editingCategoryId = null;
        $this->categoryName = '';
    }

    public function editCategory(int $categoryId): void
    {
        $category = ExpenseCategory::query()->findOrFail($categoryId);

        $this->resetErrorBag('categoryName');
        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
    }

    public function saveCategory(): void
    {
        $validated = $this->validate([
            'categoryName' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingCategoryId !== null) {
            $category = ExpenseCategory::query()->findOrFail($this->editingCategoryId);
            $category->update(['name' => $validated['categoryName']]);
        } else {
            $baseKey = Str::of($validated['categoryName'])->lower()->snake()->toString();
            $baseKey = $baseKey !== '' ? $baseKey : 'custom_category';
            $key = $baseKey;
            $suffix = 1;

            while (ExpenseCategory::query()->where('key', $key)->exists()) {
                $key = $baseKey.'_'.$suffix;
                $suffix++;
            }

            $category = ExpenseCategory::query()->create([
                'key' => $key,
                'name' => $validated['categoryName'],
                'is_system' => false,
            ]);
        }

        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->dispatch('category-saved');

        if ($this->form['expense_category_id'] === '') {
            $this->form['expense_category_id'] = (string) $category->id;
        }
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = ExpenseCategory::query()
            ->withCount(['expenses', 'quickActions'])
            ->findOrFail($categoryId);

        if ($category->is_system) {
            $this->addError('categoryName', 'System categories cannot be deleted.');

            return;
        }

        if ($category->expenses_count > 0 || $category->quick_actions_count > 0) {
            $this->addError('categoryName', 'Category cannot be deleted while it is in use.');

            return;
        }

        $deletedCategoryId = $category->id;
        $category->delete();

        if ($this->editingCategoryId === $deletedCategoryId) {
            $this->cancelCategoryForm();
        }

        if ((int) ($this->form['expense_category_id'] ?: 0) === $deletedCategoryId) {
            $this->form['expense_category_id'] = (string) ($this->categories->first()?->id ?? '');
        }

        $this->dispatch('category-saved');
    }

    public function cancelCategoryForm(): void
    {
        $this->resetErrorBag('categoryName');
        $this->editingCategoryId = null;
        $this->categoryName = '';
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
        return ExpenseCategory::query()
            ->withCount(['expenses', 'quickActions'])
            ->orderBy('is_system')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function expenses(): Collection
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

        return Auth::user()
            ->expenses()
            ->with(['car', 'category', 'attachments'])
            ->when($this->filterCategoryId, fn ($query) => $query->where('expense_category_id', (int) $this->filterCategoryId))
            ->when($periodStartDate !== null, fn ($query) => $query->whereDate('expense_date', '>=', $periodStartDate))
            ->when($periodEndDate !== null, fn ($query) => $query->whereDate('expense_date', '<=', $periodEndDate))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function filteredTotal(): float
    {
        return (float) $this->expenses->sum('amount');
    }

    #[Computed]
    public function editingAttachments(): Collection
    {
        if ($this->editingExpenseId === null) {
            return new Collection();
        }

        return Auth::user()
            ->expenses()
            ->with('attachments')
            ->findOrFail($this->editingExpenseId)
            ->attachments
            ->sortByDesc('id')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function expenseRules(): array
    {
        return [
            'form.car_id' => [
                'required',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'form.expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'form.amount' => ['required', 'numeric', 'min:0.01'],
            'form.expense_date' => ['required', 'date'],
            'form.odometer' => ['nullable', 'integer', 'min:0'],
            'form.vendor' => ['nullable', 'string', 'max:255'],
            'form.notes' => ['nullable', 'string'],
            'form.tags' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function expenseMessages(): array
    {
        return [
            'form.car_id.required' => 'Please select a car.',
            'form.expense_category_id.required' => 'Please select a category.',
            'form.amount.required' => 'Amount is required.',
            'form.expense_date.required' => 'Date is required.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function attachmentRules(): array
    {
        return [
            'newAttachments' => ['nullable', 'array'],
            'newAttachments.*' => [
                'file',
                'extensions:jpg,jpeg,png,pdf,heic,heif',
                'mimetypes:image/jpeg,image/png,image/heic,image/heif,application/pdf,application/octet-stream',
                'max:10240',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attachmentMessages(): array
    {
        return [
            'newAttachments.*.extensions' => 'Attachments must be JPG, PNG, HEIC, HEIF, or PDF files.',
            'newAttachments.*.mimetypes' => 'Attachments must be JPG, PNG, HEIC, HEIF, or PDF files.',
            'newAttachments.*.max' => 'Attachments must be 10MB or smaller.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    protected function normalizeExpenseAttributes(array $form): array
    {
        foreach (['odometer', 'vendor', 'notes'] as $field) {
            if ($form[$field] === '') {
                $form[$field] = null;
            }
        }

        $form['tags'] = collect(explode(',', (string) $form['tags']))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->values()
            ->all();

        if ($form['tags'] === []) {
            $form['tags'] = null;
        }

        return $form;
    }

    protected function syncLedgerEntry(Expense $expense): void
    {
        $account = app(ExpenseAccountResolver::class)->accountForCategory($expense->category);

        $entryAttributes = [
            'user_id' => Auth::id(),
            'car_id' => $expense->car_id,
            'account_id' => $account->id,
            'entry_date' => $expense->expense_date->format('Y-m-d'),
            'entry_type' => 'expense',
            'amount' => $expense->amount,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'reference' => $expense->vendor,
            'notes' => $expense->notes,
        ];

        $entry = $expense->ledger_entry_id !== null
            ? Auth::user()->ledgerEntries()->findOrFail($expense->ledger_entry_id)
            : new LedgerEntry();

        $entry->fill($entryAttributes);
        $entry->save();

        if ($expense->ledger_entry_id !== $entry->id) {
            $expense->update(['ledger_entry_id' => $entry->id]);
        }
    }

    protected function resetForm(): void
    {
        $this->form = [
            'car_id' => '',
            'expense_category_id' => '',
            'amount' => '',
            'expense_date' => now()->format('Y-m-d'),
            'odometer' => '',
            'vendor' => '',
            'notes' => '',
            'tags' => '',
        ];
    }

    protected function defaultOdometerForCar(int $carId): string
    {
        $car = Auth::user()->cars()->whereKey($carId)->first();

        if ($car === null) {
            return '';
        }

        $odometer = app(LatestOdometerResolver::class)->forCar($car);

        if ($odometer === null) {
            return '';
        }

        return (string) $odometer;
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Expenses') }}</flux:heading>
            <flux:subheading>{{ __('Log and review your car-related costs.') }}</flux:subheading>
        </div>

        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
            <flux:modal.trigger name="manage-expense-categories">
                <flux:button class="w-full sm:w-auto" variant="ghost" wire:click="startCreatingCategory">
                    {{ __('Manage Categories') }}
                </flux:button>
            </flux:modal.trigger>

            <flux:button class="w-full sm:w-auto" variant="primary" wire:click="startCreating" :disabled="$this->cars->isEmpty() || $this->categories->isEmpty()">
                {{ __('Add Expense') }}
            </flux:button>
        </div>
    </div>

    @if ($this->cars->isEmpty())
        <flux:card>
            <flux:text>{{ __('Add a car first before creating expenses.') }}</flux:text>
        </flux:card>
    @endif

    @if ($this->categories->isEmpty())
        <flux:card>
            <flux:text>{{ __('No expense categories found. Run database seeders to add defaults.') }}</flux:text>
        </flux:card>
    @endif

    <flux:modal :closable="false" name="manage-expense-categories" class="border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[42rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div>
                <flux:heading>{{ __('Manage Expense Categories') }}</flux:heading>
                <flux:subheading>{{ __('Create custom categories, rename labels, or remove unused custom ones.') }}</flux:subheading>
            </div>

            <div class="grid gap-5 md:grid-cols-[0.9fr_1.1fr]">
                <form wire:submit="saveCategory" class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <div>
                        <flux:heading size="sm">{{ $editingCategoryId ? __('Rename Category') : __('Add Category') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $editingCategoryId ? __('Update the selected category name.') : __('Create a custom category for expenses and quick actions.') }}
                        </flux:text>
                    </div>

                    <flux:input
                        wire:model="categoryName"
                        :label="$editingCategoryId ? __('Category Name') : __('New Category Name')"
                        type="text"
                        required
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button type="submit" variant="primary">{{ $editingCategoryId ? __('Save Rename') : __('Add Category') }}</flux:button>
                        @if ($editingCategoryId !== null)
                            <flux:button type="button" variant="ghost" wire:click="cancelCategoryForm">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>

                    @error('categoryName')
                        <flux:text class="text-sm text-rose-700 dark:text-rose-400">{{ $message }}</flux:text>
                    @enderror

                    <x-action-message on="category-saved">
                        {{ __('Saved.') }}
                    </x-action-message>
                </form>

                <div class="space-y-3">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('System categories can be renamed but not deleted. Custom categories can be deleted once unused.') }}</flux:text>

                    <div class="max-h-96 space-y-2 overflow-auto rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                        @foreach ($this->categories as $category)
                            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $category->name }}</div>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            <span class="inline-flex rounded-full border px-2 py-0.5 {{ $category->is_system ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300' : 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }}">
                                                {{ $category->is_system ? __('System') : __('Custom') }}
                                            </span>
                                            <span>{{ trans_choice('{0} Unused|{1} :count expense|[2,*] :count expenses', $category->expenses_count, ['count' => $category->expenses_count]) }}</span>
                                            <span>{{ trans_choice('{0} No quick actions|{1} :count quick action|[2,*] :count quick actions', $category->quick_actions_count, ['count' => $category->quick_actions_count]) }}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <flux:button variant="ghost" wire:click="editCategory({{ $category->id }})">{{ __('Rename') }}</flux:button>
                                        @if (! $category->is_system)
                                            <flux:button
                                                type="button"
                                                variant="ghost"
                                                class="text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:text-rose-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-200"
                                                wire:click="deleteCategory({{ $category->id }})"
                                                wire:confirm="{{ __('Delete this category? This only works when the category is unused.') }}"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" wire:click="cancelCategoryForm">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal :closable="false" wire:model="showForm" class="max-h-[90vh] overflow-y-auto border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[48rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ $editingExpenseId ? __('Edit Expense') : __('Add Expense') }}</flux:heading>
                    <flux:subheading>{{ __('Create or update manual expense entries.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveExpense" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="form.car_id" :label="__('Car')" required>
                        <flux:select.option value="">{{ __('Select car') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="form.expense_category_id" :label="__('Category')" required>
                        <flux:select.option value="">{{ __('Select category') }}</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="form.amount" :label="__('Amount')" type="number" min="0.01" step="0.01" required />
                    <flux:input wire:model="form.expense_date" :label="__('Date')" type="date" required />
                    <flux:input wire:model="form.odometer" :label="__('Odometer')" type="number" min="0" step="1" />
                    <flux:input wire:model="form.vendor" :label="__('Vendor')" type="text" />
                </div>

                <flux:input wire:model="form.tags" :label="__('Tags (comma separated)')" type="text" />
                <flux:input wire:model="form.notes" :label="__('Notes')" type="text" />

                <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <div>
                        <flux:heading size="sm">{{ __('Attachments') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add receipt photos or PDFs.') }}</flux:text>
                    </div>

                    <flux:input wire:model="newAttachments" type="file" multiple accept=".jpg,.jpeg,.png,.heic,.heif,.pdf" />

                    <div class="space-y-2">
                        <div wire:loading wire:target="newAttachments" class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Uploading selected files...') }}
                        </div>

                        @if ($errors->has('newAttachments') || $errors->has('newAttachments.*'))
                            <div class="space-y-1">
                                @foreach ($this->attachmentUploadErrorMessages() as $attachmentError)
                                    <flux:text class="text-sm text-rose-600 dark:text-rose-400">{{ $attachmentError }}</flux:text>
                                @endforeach
                            </div>
                        @endif

                        @if ($newAttachments !== [])
                            <div class="space-y-2">
                                @foreach ($newAttachments as $upload)
                                    <div class="flex items-center justify-between rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600">
                                        <span class="truncate">{{ $upload->getClientOriginalName() }}</span>
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Ready to save') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($editingExpenseId !== null)
                            @if ($this->editingAttachments->isEmpty())
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No saved attachments yet. Any selected files will be attached after you press Save Expense.') }}</flux:text>
                            @else
                                <div class="space-y-2">
                                    @foreach ($this->editingAttachments as $attachment)
                                        <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                                            <div class="min-w-0">
                                                <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $attachment->original_name }}</div>
                                                <div class="text-zinc-500 dark:text-zinc-400">
                                                    {{ strtoupper($attachment->isPreviewableImage() ? 'Image' : 'PDF') }}
                                                    ·
                                                    {{ number_format($attachment->size / 1024, 1) }} KB
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <a
                                                    href="{{ route('attachments.show', $attachment) }}"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    class="inline-flex items-center rounded-lg border border-zinc-300 px-3 py-1.5 text-sm font-medium text-zinc-800 transition hover:bg-zinc-100 dark:border-zinc-600 dark:text-zinc-100 dark:hover:bg-zinc-800"
                                                >
                                                    {{ __('Open') }}
                                                </a>
                                                <flux:button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-rose-700 hover:bg-rose-50 hover:text-rose-800 dark:text-rose-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-200"
                                                    wire:click="deleteAttachment({{ $attachment->id }})"
                                                    wire:confirm="{{ __('Delete this attachment?') }}"
                                                >
                                                    {{ __('Delete') }}
                                                </flux:button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="newAttachments">
                            <span wire:loading.remove wire:target="newAttachments">{{ __('Save Expense') }}</span>
                            <span wire:loading wire:target="newAttachments">{{ __('Upload in progress...') }}</span>
                        </flux:button>
                        <x-action-message on="expense-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($editingExpenseId !== null && ! $confirmingDelete)
                            <flux:button type="button" variant="danger" wire:click="confirmDeleteEditing">{{ __('Delete') }}</flux:button>
                        @elseif ($editingExpenseId !== null && $confirmingDelete)
                            <flux:text class="text-red-600 dark:text-red-400">{{ __('Confirm delete this expense?') }}</flux:text>
                            <flux:button type="button" variant="danger" wire:click="deleteEditingExpense">{{ __('Confirm Delete') }}</flux:button>
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
                    <flux:select wire:model.live="filterCategoryId" :label="__('Filter Category')">
                        <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </details>
        </div>

        <div class="hidden flex-wrap items-end gap-3 md:flex">
            <div class="w-full sm:w-64">
                <flux:select wire:model.live="filterCategoryId" :label="__('Filter Category')">
                    <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
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

        <flux:text>{{ __('Filtered total') }}: <strong>{{ $this->formatCurrency($this->filteredTotal) }}</strong></flux:text>
        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Tap any expense to edit it.') }}</flux:text>
    </flux:card>

    @if ($this->expenses->isEmpty())
        <flux:card>
            <flux:text>{{ __('No expenses found for the selected filters.') }}</flux:text>
        </flux:card>
    @else
        <div class="space-y-3 md:hidden">
            @foreach ($this->expenses as $expense)
                <button
                    type="button"
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                    wire:click="editExpense({{ $expense->id }})"
                    wire:key="expense-card-{{ $expense->id }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $expense->category->name }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $expense->expense_date->format('d-m-Y') }}</div>
                            @if ($expense->attachments->isNotEmpty())
                                <div class="text-xs text-sky-700 dark:text-sky-300">{{ __('Docs attached') }}</div>
                            @endif
                        </div>
                        <div class="text-right font-semibold">{{ $this->formatCurrency($expense->amount) }}</div>
                    </div>
                    <dl class="mt-3 grid gap-2 text-sm">
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Vendor') }}</dt>
                            <dd>{{ $expense->vendor ?: __('N/A') }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Odometer') }}</dt>
                                <dd>{{ $expense->odometer !== null ? number_format((float) $expense->odometer) : __('N/A') }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Tags') }}</dt>
                                <dd>{{ implode(', ', $expense->tags ?? []) ?: __('N/A') }}</dd>
                            </div>
                        </div>
                    </dl>
                </button>
            @endforeach
        </div>
        <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
            <table class="w-full min-w-[980px] text-left text-sm">
                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 font-medium">{{ __('Date') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Category') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Vendor') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Odometer') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Tags') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->expenses as $expense)
                        <tr
                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                            tabindex="0"
                            wire:click="editExpense({{ $expense->id }})"
                            wire:key="expense-row-{{ $expense->id }}"
                        >
                            <td class="px-3 py-2">{{ $expense->expense_date->format('d-m-Y') }}</td>
                            <td class="px-3 py-2">
                                <div>{{ $expense->category->name }}</div>
                                @if ($expense->attachments->isNotEmpty())
                                    <div class="text-xs text-sky-700 dark:text-sky-300">{{ __('Docs attached') }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $expense->vendor ?: __('N/A') }}</td>
                            <td class="px-3 py-2">{{ $expense->odometer !== null ? number_format((float) $expense->odometer) : __('N/A') }}</td>
                            <td class="px-3 py-2">{{ implode(', ', $expense->tags ?? []) ?: __('N/A') }}</td>
                            <td class="px-3 py-2 text-right">{{ $this->formatCurrency($expense->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
