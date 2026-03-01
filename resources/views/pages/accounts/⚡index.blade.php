<?php

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public bool $confirmingDelete = false;
    public ?int $editingAccountId = null;
    public ?string $deleteGuardMessage = null;

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
        $this->editingAccountId = null;
        $this->confirmingDelete = false;
        $this->deleteGuardMessage = null;
        $this->resetForm();
        $this->showForm = true;
    }

    public function editAccount(int $accountId): void
    {
        $account = $this->findEditableAccount($accountId);

        $this->editingAccountId = $account->id;
        $this->confirmingDelete = false;
        $this->deleteGuardMessage = null;
        $this->form = [
            'name' => $account->name,
            'group' => $account->group,
            'is_active' => $account->is_active,
            'is_system' => $account->is_system,
            'key' => $account->key,
        ];
        $this->showForm = true;
    }

    public function saveAccount(): void
    {
        $rules = [
            'form.name' => ['required', 'string', 'max:255'],
            'form.is_active' => ['boolean'],
        ];

        if ($this->editingAccountId === null) {
            $rules['form.group'] = ['required', Rule::in(['expense', 'income'])];
        }

        $validated = $this->validate($rules)['form'];

        if ($this->editingAccountId !== null) {
            $account = $this->findEditableAccount($this->editingAccountId);

            $account->update([
                'name' => $validated['name'],
                'is_active' => $account->is_system ? true : (bool) $validated['is_active'],
            ]);
        } else {
            Account::query()->create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'key' => $this->generateCustomKey($validated['name'], $validated['group']),
                'group' => $validated['group'],
                'is_system' => false,
                'is_active' => (bool) $validated['is_active'],
            ]);
        }

        $this->cancelForm();
        $this->dispatch('account-saved');
    }

    public function confirmDeleteEditing(): void
    {
        if ($this->editingAccountId === null) {
            return;
        }

        $account = $this->findEditableAccount($this->editingAccountId);

        if ($account->is_system) {
            $this->deleteGuardMessage = __('System accounts cannot be deleted.');

            return;
        }

        if ($this->accountIsUsed($account)) {
            $this->deleteGuardMessage = __('This account is already used in ledger entries or recurring schedules. Archive it instead of deleting it.');

            return;
        }

        $this->deleteGuardMessage = null;
        $this->confirmingDelete = true;
    }

    public function cancelDeleteEditing(): void
    {
        $this->confirmingDelete = false;
        $this->deleteGuardMessage = null;
    }

    public function deleteEditingAccount(): void
    {
        if ($this->editingAccountId === null) {
            return;
        }

        $account = $this->findEditableAccount($this->editingAccountId);

        if ($account->is_system || $this->accountIsUsed($account)) {
            $this->confirmDeleteEditing();

            return;
        }

        $account->delete();
        $this->cancelForm();
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingAccountId = null;
        $this->confirmingDelete = false;
        $this->deleteGuardMessage = null;
        $this->resetForm();
    }

    #[Computed]
    public function systemAccounts(): Collection
    {
        return Account::query()
            ->where('is_system', true)
            ->orderBy('group')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function customAccounts(): Collection
    {
        return Account::query()
            ->where('is_system', false)
            ->where('user_id', Auth::id())
            ->orderByDesc('is_active')
            ->orderBy('group')
            ->orderBy('name')
            ->get();
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'group' => 'expense',
            'is_active' => true,
            'is_system' => false,
            'key' => '',
        ];
    }

    protected function findEditableAccount(int $accountId): Account
    {
        return Account::query()
            ->whereKey($accountId)
            ->where(function ($query): void {
                $query->where('is_system', true)
                    ->orWhere(fn ($customQuery) => $customQuery
                        ->where('is_system', false)
                        ->where('user_id', Auth::id()));
            })
            ->firstOrFail();
    }

    protected function accountIsUsed(Account $account): bool
    {
        return $account->ledgerEntries()->exists() || $account->recurringTransactions()->exists();
    }

    protected function generateCustomKey(string $name, string $group): string
    {
        $baseKey = Str::slug($name, '_').'_'.$group;
        $candidateKey = $baseKey;
        $suffix = 2;

        while (Account::query()->where('key', $candidateKey)->exists()) {
            $candidateKey = $baseKey.'_'.$suffix;
            $suffix++;
        }

        return $candidateKey;
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Accounts') }}</flux:heading>
            <flux:subheading>{{ __('Rename protected system accounts and manage your custom ledger accounts.') }}</flux:subheading>
        </div>

        <flux:button class="w-full sm:w-auto" variant="primary" wire:click="startCreating">
            {{ __('Add Account') }}
        </flux:button>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card class="space-y-1">
            <flux:text>{{ __('System Accounts') }}</flux:text>
            <flux:heading>{{ $this->systemAccounts->count() }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text>{{ __('Custom Accounts') }}</flux:text>
            <flux:heading>{{ $this->customAccounts->count() }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text>{{ __('Active Custom Accounts') }}</flux:text>
            <flux:heading>{{ $this->customAccounts->where('is_active', true)->count() }}</flux:heading>
        </flux:card>
    </div>

    <flux:modal :closable="false" wire:model="showForm" class="max-h-[90vh] overflow-y-auto border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[44rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ $editingAccountId !== null ? __('Manage Account') : __('Add Account') }}</flux:heading>
                    <flux:subheading>{{ $editingAccountId !== null ? __('System account keys are protected. Custom accounts can be archived or deleted when unused.') : __('Create a custom income or expense account.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveAccount" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="form.name" :label="__('Name')" type="text" required />

                    @if ($editingAccountId === null)
                        <flux:select wire:model="form.group" :label="__('Group')" required>
                            <flux:select.option value="expense">{{ __('Expense') }}</flux:select.option>
                            <flux:select.option value="income">{{ __('Income') }}</flux:select.option>
                        </flux:select>
                    @else
                        <flux:input :label="__('Group')" type="text" :value="$form['group'] === 'income' ? __('Income') : __('Expense')" disabled />
                    @endif

                    <flux:input wire:model="form.key" :label="__('Internal Key')" type="text" disabled />

                    @if (! $form['is_system'])
                        <div class="flex items-end">
                            <flux:checkbox wire:model="form.is_active" :label="__('Active')" />
                        </div>
                    @else
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('System accounts stay active because other app features depend on their keys.') }}
                        </div>
                    @endif
                </div>

                @if ($deleteGuardMessage !== null)
                    <flux:text class="text-amber-700 dark:text-amber-300">{{ $deleteGuardMessage }}</flux:text>
                @endif

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="primary">
                            {{ $editingAccountId !== null ? __('Save Account') : __('Create Account') }}
                        </flux:button>
                        <x-action-message on="account-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($editingAccountId !== null && ! $form['is_system'] && ! $confirmingDelete)
                            <flux:button type="button" variant="ghost" wire:click="confirmDeleteEditing">{{ __('Delete') }}</flux:button>
                        @elseif ($editingAccountId !== null && ! $form['is_system'] && $confirmingDelete)
                            <flux:text class="text-red-600 dark:text-red-400">{{ __('Confirm delete this custom account?') }}</flux:text>
                            <flux:button type="button" variant="danger" wire:click="deleteEditingAccount">{{ __('Confirm Delete') }}</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="cancelDeleteEditing">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:card class="space-y-2">
        <flux:text>{{ __('Tap any account to manage it. System account names are editable, but their keys stay protected.') }}</flux:text>
    </flux:card>

    <div class="space-y-6">
        <div class="space-y-3">
            <div>
                <flux:heading size="lg">{{ __('System Accounts') }}</flux:heading>
                <flux:subheading>{{ __('Used by built-in app features. Rename labels only.') }}</flux:subheading>
            </div>

            <div class="space-y-3 md:hidden">
                @foreach ($this->systemAccounts as $account)
                    <button
                        type="button"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                        wire:click="editAccount({{ $account->id }})"
                        wire:key="system-account-card-{{ $account->id }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ $account->name }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $account->group === 'income' ? __('Income') : __('Expense') }}</div>
                            </div>
                            <div class="text-right text-xs text-zinc-500 dark:text-zinc-400">{{ $account->key }}</div>
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ __('Name') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Group') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Internal Key') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->systemAccounts as $account)
                            <tr class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70" wire:click="editAccount({{ $account->id }})" wire:key="system-account-row-{{ $account->id }}">
                                <td class="px-3 py-2">{{ $account->name }}</td>
                                <td class="px-3 py-2">{{ $account->group === 'income' ? __('Income') : __('Expense') }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $account->key }}</td>
                                <td class="px-3 py-2">{{ __('Protected') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3">
            <div>
                <flux:heading size="lg">{{ __('Custom Accounts') }}</flux:heading>
                <flux:subheading>{{ __('Create your own additional income or expense labels for reimbursements and recurring schedules.') }}</flux:subheading>
            </div>

            @if ($this->customAccounts->isEmpty())
                <flux:card>
                    <flux:text>{{ __('No custom accounts yet.') }}</flux:text>
                </flux:card>
            @else
                <div class="space-y-3 md:hidden">
                    @foreach ($this->customAccounts as $account)
                        <button
                            type="button"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                            wire:click="editAccount({{ $account->id }})"
                            wire:key="custom-account-card-{{ $account->id }}"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-medium">{{ $account->name }}</div>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $account->group === 'income' ? __('Income') : __('Expense') }}</div>
                                </div>
                                <div class="text-right text-sm">
                                    <div class="{{ $account->is_active ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                        {{ $account->is_active ? __('Active') : __('Archived') }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $account->key }}</div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>

                <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('Name') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Group') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Internal Key') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->customAccounts as $account)
                                <tr class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70" wire:click="editAccount({{ $account->id }})" wire:key="custom-account-row-{{ $account->id }}">
                                    <td class="px-3 py-2">{{ $account->name }}</td>
                                    <td class="px-3 py-2">{{ $account->group === 'income' ? __('Income') : __('Expense') }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $account->key }}</td>
                                    <td class="px-3 py-2">{{ $account->is_active ? __('Active') : __('Archived') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</section>
