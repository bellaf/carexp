<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $showForm = false;
    public bool $confirmingDelete = false;
    public ?int $editingUserId = null;

    /**
     * @var array<string, mixed>
     */
    public array $form = [];

    public function mount(): void
    {
        abort_unless(Auth::user()?->canManageUsers(), 403);
        $this->resetForm();
    }

    public function editUser(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->form = [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'is_approved' => $user->is_approved,
        ];

        $this->showForm = true;
        $this->confirmingDelete = false;
    }

    public function saveUser(): void
    {
        abort_if($this->editingUserId === null, 404);

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255'],
            'form.is_admin' => ['boolean'],
            'form.is_approved' => ['boolean'],
        ])['form'];

        $user = User::query()->findOrFail($this->editingUserId);
        $currentUser = Auth::user();

        if ($user->is($currentUser) && ! $validated['is_admin']) {
            $this->addError('form.is_admin', __('You cannot remove your own administrator access.'));

            return;
        }

        if ($user->is($currentUser) && ! $validated['is_approved']) {
            $this->addError('form.is_approved', __('You cannot revoke your own approval.'));

            return;
        }

        $approvalUpdates = [];

        if ((bool) $validated['is_approved'] && ! $user->is_approved) {
            $approvalUpdates['approved_at'] = now();
            $approvalUpdates['approved_by'] = $currentUser->id;
        }

        if (! (bool) $validated['is_approved']) {
            $approvalUpdates['approved_at'] = null;
            $approvalUpdates['approved_by'] = null;
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => (bool) $validated['is_admin'],
            'is_approved' => (bool) $validated['is_approved'],
            ...$approvalUpdates,
        ]);

        $this->cancelForm();
        $this->dispatch('user-saved');
    }

    public function confirmDeleteEditing(): void
    {
        if ($this->editingUserId === null) {
            return;
        }

        $this->confirmingDelete = true;
    }

    public function cancelDeleteEditing(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteEditingUser(): void
    {
        abort_if($this->editingUserId === null, 404);

        $user = User::query()->findOrFail($this->editingUserId);

        if ($user->is(Auth::user())) {
            $this->addError('form.name', __('You cannot delete your own account from user administration.'));

            return;
        }

        $user->delete();
        $this->cancelForm();
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingUserId = null;
        $this->confirmingDelete = false;
        $this->resetForm();
    }

    #[Computed]
    public function users(): Collection
    {
        return User::query()
            ->orderByDesc('is_admin')
            ->orderByDesc('is_approved')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->users->where('is_approved', false)->count();
    }

    #[Computed]
    public function adminCount(): int
    {
        return $this->users->where('is_admin', true)->count();
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'email' => '',
            'is_admin' => false,
            'is_approved' => false,
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Users') }}</flux:heading>
        <flux:subheading>{{ __('Approve, reject, and manage access for system users.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card class="space-y-1">
            <flux:text>{{ __('Total Users') }}</flux:text>
            <flux:heading>{{ $this->users->count() }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text>{{ __('Pending Approval') }}</flux:text>
            <flux:heading>{{ $this->pendingCount }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text>{{ __('Administrators') }}</flux:text>
            <flux:heading>{{ $this->adminCount }}</flux:heading>
        </flux:card>
    </div>

    <flux:modal :closable="false" wire:model="showForm" class="max-h-[90vh] overflow-y-auto border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[42rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ __('Manage User') }}</flux:heading>
                    <flux:subheading>{{ __('Approve access or change administrator status.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveUser" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="form.name" :label="__('Name')" type="text" required />
                    <flux:input wire:model="form.email" :label="__('Email')" type="email" required />
                </div>

                <div class="space-y-3">
                    <flux:checkbox wire:model="form.is_approved" :label="__('Approved')" />
                    <flux:checkbox wire:model="form.is_admin" :label="__('Administrator')" />
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <flux:button type="submit" variant="primary">{{ __('Save User') }}</flux:button>
                        <x-action-message on="user-saved">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($editingUserId !== null && ! $confirmingDelete)
                            <flux:button type="button" variant="danger" wire:click="confirmDeleteEditing">{{ __('Delete') }}</flux:button>
                        @elseif ($editingUserId !== null && $confirmingDelete)
                            <flux:text class="text-red-600 dark:text-red-400">{{ __('Confirm delete this user?') }}</flux:text>
                            <flux:button type="button" variant="danger" wire:click="deleteEditingUser">{{ __('Confirm Delete') }}</flux:button>
                            <flux:button type="button" variant="ghost" wire:click="cancelDeleteEditing">{{ __('Cancel') }}</flux:button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </flux:modal>

    @if ($this->users->isEmpty())
        <flux:card>
            <flux:text>{{ __('No users found.') }}</flux:text>
        </flux:card>
    @else
        <flux:card class="space-y-2">
            <flux:text>{{ __('Tap any user to manage approval and access.') }}</flux:text>
        </flux:card>

        <div class="space-y-3 md:hidden">
            @foreach ($this->users as $user)
                <button
                    type="button"
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                    wire:click="editUser({{ $user->id }})"
                    wire:key="user-card-{{ $user->id }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $user->name }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $user->email }}</div>
                        </div>
                        <div class="text-right text-sm">
                            <div class="font-medium">{{ $user->is_admin ? __('Admin') : __('User') }}</div>
                            <div class="{{ $user->is_approved ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $user->is_approved ? __('Approved') : __('Pending') }}
                            </div>
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
                        <th class="px-3 py-2 font-medium">{{ __('Email') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Role') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                        <th class="px-3 py-2 font-medium">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->users as $user)
                        <tr
                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                            wire:click="editUser({{ $user->id }})"
                            wire:key="user-row-{{ $user->id }}"
                        >
                            <td class="px-3 py-2">{{ $user->name }}</td>
                            <td class="px-3 py-2">{{ $user->email }}</td>
                            <td class="px-3 py-2">{{ $user->is_admin ? __('Admin') : __('User') }}</td>
                            <td class="px-3 py-2">{{ $user->is_approved ? __('Approved') : __('Pending') }}</td>
                            <td class="px-3 py-2">{{ $user->created_at?->format('d-m-Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
