<?php

use App\Models\Account;
use App\Models\Attachment;
use App\Models\LedgerEntry;
use App\Models\VehicleObligation;
use App\Support\AttachmentManager;
use App\Support\CurrencyFormatter;
use App\Support\VehicleObligationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public bool $showForm = false;
    public ?int $editingObligationId = null;
    public string $filterStatus = 'active';
    public array $newAttachments = [];

    /**
     * @var array<string, string>
     */
    public array $obligationTypeOptions = [
        'insurance' => 'Insurance',
        'tax' => 'Tax / Registration',
        'mot' => 'MOT / Inspection',
    ];

    /**
     * @var array<string, string>
     */
    public array $filterStatusOptions = [
        'active' => 'Active',
        'due_soon' => 'Due Soon',
        'overdue' => 'Overdue',
        'completed' => 'Completed',
        'inactive' => 'Inactive',
        'all' => 'All',
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
        $this->editingObligationId = null;
        $this->resetForm();

        if ($this->cars->isNotEmpty()) {
            $this->form['car_id'] = (string) $this->cars->first()->id;
        }

        $this->showForm = true;
    }

    public function editObligation(int $obligationId): void
    {
        $obligation = Auth::user()->vehicleObligations()->with('ledgerEntry')->findOrFail($obligationId);

        $this->editingObligationId = $obligation->id;
        $this->form = [
            'car_id' => (string) $obligation->car_id,
            'obligation_type' => $obligation->obligation_type,
            'provider' => $obligation->provider ?? '',
            'reference' => $obligation->reference ?? '',
            'start_date' => $obligation->start_date?->format('Y-m-d') ?? '',
            'due_date' => $obligation->due_date->format('Y-m-d'),
            'amount' => $obligation->amount !== null ? (string) $obligation->amount : '',
            'notes' => $obligation->notes ?? '',
            'is_active' => $obligation->is_active,
        ];

        $this->showForm = true;
    }

    public function saveObligation(): void
    {
        $validated = $this->validate(
            array_merge($this->rules(), $this->attachmentRules()),
            array_merge($this->messages(), $this->attachmentMessages()),
        );
        $form = $validated['form'];
        $normalized = $this->normalizeObligationAttributes($form);
        $attributes = $normalized['attributes'];
        $amount = $normalized['amount'];

        DB::transaction(function () use ($attributes, $amount): void {
            if ($this->editingObligationId !== null) {
                $obligation = Auth::user()->vehicleObligations()->findOrFail($this->editingObligationId);
                $obligation->update($attributes);
            } else {
                $obligation = Auth::user()->vehicleObligations()->create($attributes);
            }

            $this->syncCompletedObligationLedgerEntry($obligation, $amount);
            app(AttachmentManager::class)->storeMany($obligation, $this->newAttachments);
        });

        $this->cancelForm();
        $this->dispatch('vehicle-obligation-saved');
    }

    public function deleteObligation(int $obligationId): void
    {
        DB::transaction(function () use ($obligationId): void {
            $obligation = Auth::user()->vehicleObligations()->findOrFail($obligationId);

            if ($obligation->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($obligation->ledger_entry_id)->delete();
            }

            $obligation->delete();
        });

        $this->cancelForm();
    }

    public function renewObligation(int $obligationId): void
    {
        DB::transaction(function () use ($obligationId): void {
            $obligation = Auth::user()->vehicleObligations()->findOrFail($obligationId);
            $amount = $obligation->amount !== null ? (float) $obligation->amount : null;

            $nextDueDate = CarbonImmutable::parse($obligation->due_date)->addYearNoOverflow();
            $nextStartDate = $obligation->start_date !== null
                ? CarbonImmutable::parse($obligation->start_date)->addYearNoOverflow()->toDateString()
                : null;

            $obligation->update([
                'is_active' => false,
                'completed_at' => now(),
            ]);

            $this->syncCompletedObligationLedgerEntry($obligation->fresh(), $amount);

            Auth::user()->vehicleObligations()->create([
                'car_id' => $obligation->car_id,
                'renewed_from_id' => $obligation->id,
                'obligation_type' => $obligation->obligation_type,
                'provider' => $obligation->provider,
                'reference' => $obligation->reference,
                'start_date' => $nextStartDate,
                'due_date' => $nextDueDate->toDateString(),
                'amount' => $obligation->amount,
                'notes' => $obligation->notes,
                'is_active' => true,
                'completed_at' => null,
            ]);
        });

        $this->cancelForm();
        $this->dispatch('vehicle-obligation-renewed');
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingObligationId = null;
        $this->newAttachments = [];
        $this->resetForm();
    }

    public function deleteAttachment(int $attachmentId): void
    {
        if ($this->editingObligationId === null) {
            return;
        }

        $obligation = Auth::user()->vehicleObligations()->with('attachments')->findOrFail($this->editingObligationId);
        $attachment = $obligation->attachments()->findOrFail($attachmentId);

        app(AttachmentManager::class)->delete($attachment);

        unset($this->editingAttachments);
    }

    public function obligationTypeLabel(string $obligationType): string
    {
        return $this->obligationTypeOptions[$obligationType] ?? ucfirst($obligationType);
    }

    public function obligationStatus(VehicleObligation $obligation): string
    {
        return VehicleObligationStatus::status($obligation);
    }

    public function obligationStatusLabel(VehicleObligation $obligation): string
    {
        return VehicleObligationStatus::label($obligation);
    }

    public function obligationStatusClasses(VehicleObligation $obligation): string
    {
        return match ($this->obligationStatus($obligation)) {
            'overdue' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
            'due_soon' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
            'completed' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300',
            'inactive' => 'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
            default => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
        };
    }

    public function formatCurrency(float|int|string|null $amount): string
    {
        return CurrencyFormatter::format($amount, Auth::user()->preferred_currency);
    }

    #[Computed]
    public function cars(): Collection
    {
        return Auth::user()->cars()->where('is_archived', false)->orderByDesc('is_default')->orderBy('make')->orderBy('model')->get();
    }

    #[Computed]
    public function obligations(): Collection
    {
        $obligations = Auth::user()->vehicleObligations()
            ->with(['car', 'ledgerEntry', 'attachments'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if ($this->filterStatus === 'all') {
            return $obligations;
        }

        return $obligations
            ->filter(function (VehicleObligation $obligation): bool {
                $status = VehicleObligationStatus::status($obligation);

                return $this->filterStatus === 'active'
                    ? $obligation->is_active
                    : $status === $this->filterStatus;
            })
            ->values();
    }

    #[Computed]
    public function stats(): array
    {
        $obligations = Auth::user()->vehicleObligations()->get();

        $overdue = $obligations->filter(fn (VehicleObligation $obligation): bool => VehicleObligationStatus::status($obligation) === 'overdue')->count();
        $dueSoon = $obligations->filter(fn (VehicleObligation $obligation): bool => VehicleObligationStatus::status($obligation) === 'due_soon')->count();
        $active = $obligations->where('is_active', true)->count();

        return [
            'overdue' => $overdue,
            'due_soon' => $dueSoon,
            'active' => $active,
        ];
    }

    #[Computed]
    public function editingAttachments(): Collection
    {
        if ($this->editingObligationId === null) {
            return new Collection();
        }

        return Auth::user()
            ->vehicleObligations()
            ->with('attachments')
            ->findOrFail($this->editingObligationId)
            ->attachments
            ->sortByDesc('id')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'form.car_id' => [
                'required',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'form.obligation_type' => ['required', Rule::in(array_keys($this->obligationTypeOptions))],
            'form.provider' => ['nullable', 'string', 'max:255'],
            'form.reference' => ['nullable', 'string', 'max:255'],
            'form.start_date' => ['nullable', 'date'],
            'form.due_date' => ['required', 'date'],
            'form.amount' => ['nullable', 'numeric', 'min:0'],
            'form.notes' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'form.car_id.required' => 'Please select a car.',
            'form.obligation_type.required' => 'Please select an obligation type.',
            'form.due_date.required' => 'The due date is required.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function attachmentRules(): array
    {
        return [
            'newAttachments' => ['nullable', 'array'],
            'newAttachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attachmentMessages(): array
    {
        return [
            'newAttachments.*.mimes' => 'Attachments must be JPG, PNG, or PDF files.',
            'newAttachments.*.max' => 'Attachments must be 10MB or smaller.',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array{attributes: array<string, mixed>, amount: ?float}
     */
    protected function normalizeObligationAttributes(array $form): array
    {
        foreach (['provider', 'reference', 'start_date', 'amount', 'notes'] as $field) {
            if ($form[$field] === '') {
                $form[$field] = null;
            }
        }

        return [
            'attributes' => [
                'car_id' => (int) $form['car_id'],
                'obligation_type' => (string) $form['obligation_type'],
                'provider' => $form['provider'],
                'reference' => $form['reference'],
                'start_date' => $form['start_date'],
                'due_date' => $form['due_date'],
                'amount' => $form['amount'] !== null ? (float) $form['amount'] : null,
                'notes' => $form['notes'],
                'is_active' => (bool) $form['is_active'],
            ],
            'amount' => $form['amount'] !== null ? (float) $form['amount'] : null,
        ];
    }

    protected function syncCompletedObligationLedgerEntry(VehicleObligation $obligation, ?float $amount): void
    {
        if ($obligation->completed_at === null) {
            if ($obligation->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($obligation->ledger_entry_id)->delete();
                $obligation->update(['ledger_entry_id' => null]);
            }

            return;
        }

        $this->syncLedgerEntry($obligation, $amount);
    }

    protected function syncLedgerEntry(VehicleObligation $obligation, ?float $amount): void
    {
        if ($amount === null || $amount <= 0) {
            if ($obligation->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($obligation->ledger_entry_id)->delete();
                $obligation->update(['ledger_entry_id' => null]);
            }

            return;
        }

        $account = Account::query()->firstOrCreate(
            ['key' => $this->accountKeyForType($obligation->obligation_type)],
            [
                'user_id' => null,
                'name' => $this->defaultAccountName($obligation->obligation_type),
                'group' => 'expense',
                'is_system' => true,
                'is_active' => true,
            ],
        );

        $entryAttributes = [
            'user_id' => Auth::id(),
            'car_id' => $obligation->car_id,
            'account_id' => $account->id,
            'entry_date' => $obligation->due_date->format('Y-m-d'),
            'entry_type' => 'expense',
            'amount' => $amount,
            'source_type' => 'vehicle_obligation',
            'source_id' => $obligation->id,
            'reference' => $obligation->reference ?: $obligation->provider,
            'notes' => $obligation->notes,
        ];

        $entry = $obligation->ledger_entry_id !== null
            ? Auth::user()->ledgerEntries()->findOrFail($obligation->ledger_entry_id)
            : new LedgerEntry();

        $entry->fill($entryAttributes);
        $entry->save();

        if ($obligation->ledger_entry_id !== $entry->id) {
            $obligation->update(['ledger_entry_id' => $entry->id]);
        }
    }

    protected function accountKeyForType(string $obligationType): string
    {
        return match ($obligationType) {
            'insurance' => 'insurance_expense',
            'tax' => 'tax_registration_expense',
            default => 'inspection_mot_expense',
        };
    }

    protected function defaultAccountName(string $obligationType): string
    {
        return match ($obligationType) {
            'insurance' => 'Insurance',
            'tax' => 'Tax/Registration',
            default => 'MOT/Inspection',
        };
    }

    protected function resetForm(): void
    {
        $this->form = [
            'car_id' => '',
            'obligation_type' => 'insurance',
            'provider' => '',
            'reference' => '',
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addYear()->format('Y-m-d'),
            'amount' => '',
            'notes' => '',
            'is_active' => true,
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Obligations') }}</flux:heading>
            <flux:subheading>{{ __('Track annual tax, MOT, and insurance renewals.') }}</flux:subheading>
        </div>

        <flux:button class="w-full sm:w-auto" variant="primary" wire:click="startCreating" :disabled="$this->cars->isEmpty()">
            {{ __('Add Obligation') }}
        </flux:button>
    </div>

    @if ($this->cars->isEmpty())
        <flux:card>
            <flux:text>{{ __('Add a car first before creating obligations.') }}</flux:text>
        </flux:card>
    @endif

    @if ($showForm)
        <flux:modal class="md:w-[40rem]" variant="flyout" :closable="false" wire:model.self="showForm">
            <div class="space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading>{{ $editingObligationId ? __('Edit Obligation') : __('Add Obligation') }}</flux:heading>
                        <flux:text>{{ __('Keep annual renewals and compliance items in one place.') }}</flux:text>
                    </div>

                    <flux:button variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
                </div>

                <form wire:submit="saveObligation" class="space-y-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:select wire:model="form.car_id" :label="__('Car')" required>
                            <flux:select.option value="">{{ __('Select car') }}</flux:select.option>
                            @foreach ($this->cars as $car)
                                <flux:select.option :value="(string) $car->id">{{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="form.obligation_type" :label="__('Type')" required>
                            @foreach ($obligationTypeOptions as $value => $label)
                                <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="form.provider" :label="__('Provider')" type="text" />
                        <flux:input wire:model="form.reference" :label="__('Reference')" type="text" />
                        <flux:input wire:model="form.start_date" :label="__('Start Date')" type="date" />
                        <flux:input wire:model="form.due_date" :label="__('Due Date')" type="date" required />
                        <flux:input wire:model="form.amount" :label="__('Renewal Cost')" type="number" min="0" step="0.01" />
                    </div>

                    <div class="space-y-2 rounded-xl border border-sky-200 bg-sky-50/80 p-4 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
                        <div>{{ __('Saving an obligation stores the reminder only. The cost is posted to the ledger when you renew/complete the obligation, not when you first create it.') }}</div>
                        <div>{{ __('Start Date is when the covered period begins. Due Date is when the renewal or expiry comes around.') }}</div>
                    </div>

                    <flux:textarea wire:model="form.notes" :label="__('Notes')" rows="4" />

                    <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div>
                            <flux:heading size="sm">{{ __('Attachments') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add policy documents, MOT paperwork, or renewal receipts.') }}</flux:text>
                        </div>

                        <flux:input wire:model="newAttachments" type="file" multiple accept=".jpg,.jpeg,.png,.pdf" />

                        <div class="space-y-2">
                            <div wire:loading wire:target="newAttachments" class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Uploading selected files...') }}
                            </div>

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

                            @if ($editingObligationId !== null)
                                @if ($this->editingAttachments->isEmpty())
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No saved attachments yet.') }}</flux:text>
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

                    <flux:checkbox wire:model="form.is_active" :label="__('Active')" />

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <div class="flex items-center gap-3">
                            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                        </div>

                        @if ($editingObligationId)
                            <div class="flex items-center gap-2">
                                @php
                                    $editingObligation = $editingObligationId !== null ? Auth::user()->vehicleObligations()->find($editingObligationId) : null;
                                @endphp
                                @if ($editingObligation?->is_active && $editingObligation?->completed_at === null)
                                    <flux:button
                                        type="button"
                                        variant="primary"
                                        wire:click="renewObligation({{ $editingObligationId }})"
                                        wire:confirm="{{ __('Mark this obligation as renewed and create the next year entry?') }}"
                                    >
                                        {{ __('Renew for Next Year') }}
                                    </flux:button>
                                @endif
                                <flux:button
                                    type="button"
                                    variant="danger"
                                    wire:click="deleteObligation({{ $editingObligationId }})"
                                    wire:confirm="{{ __('Delete this obligation and any linked ledger transaction?') }}"
                                >
                                    {{ __('Delete Obligation') }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </form>
            </div>
        </flux:modal>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
            <flux:text>{{ __('Overdue') }}</flux:text>
            <flux:heading size="lg">{{ $this->stats['overdue'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
            <flux:text>{{ __('Due Soon') }}</flux:text>
            <flux:heading size="lg">{{ $this->stats['due_soon'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
            <flux:text>{{ __('Active') }}</flux:text>
            <flux:heading size="lg">{{ $this->stats['active'] }}</flux:heading>
        </div>
    </div>

    <flux:card class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading>{{ __('Renewal List') }}</flux:heading>
                <flux:text>{{ __('Tap a record to edit it.') }}</flux:text>
            </div>

            <div class="w-full sm:w-56">
                <flux:select wire:model.live="filterStatus" :label="__('Status')">
                    @foreach ($filterStatusOptions as $value => $label)
                        <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @if ($this->obligations->isEmpty())
            <flux:text>{{ __('No obligations match the current filter.') }}</flux:text>
        @else
            <div class="space-y-3 md:hidden">
                @foreach ($this->obligations as $obligation)
                    <button
                        type="button"
                        wire:click="editObligation({{ $obligation->id }})"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left transition hover:border-zinc-300 hover:bg-zinc-100/70 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:border-zinc-600 dark:hover:bg-zinc-900"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->obligationTypeLabel($obligation->obligation_type) }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ trim(collect([$obligation->car?->year, $obligation->car?->make, $obligation->car?->model])->filter()->implode(' ')) }}</div>
                                @if ($obligation->attachments->isNotEmpty())
                                    <div class="text-xs text-sky-700 dark:text-sky-300">{{ __('Docs attached') }}</div>
                                @endif
                            </div>
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $this->obligationStatusClasses($obligation) }}">{{ $this->obligationStatusLabel($obligation) }}</span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Due Date') }}</dt>
                                <dd>{{ $obligation->due_date->format('d-m-Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Cost') }}</dt>
                                <dd>{{ $this->formatCurrency($obligation->amount) }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Provider') }}</dt>
                                <dd>{{ $obligation->provider ?: __('Not set') }}</dd>
                            </div>
                            <div>
                                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Reference') }}</dt>
                                <dd>{{ $obligation->reference ?: __('Not set') }}</dd>
                            </div>
                        </dl>
                    </button>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                        <tr>
                            <th class="px-3 py-2 font-medium">{{ __('Type') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Due Date') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Provider') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Reference') }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ __('Cost') }}</th>
                            <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->obligations as $obligation)
                            <tr
                                wire:click="editObligation({{ $obligation->id }})"
                                class="cursor-pointer border-t border-zinc-200 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                            >
                                <td class="px-3 py-2">
                                    <div>{{ $this->obligationTypeLabel($obligation->obligation_type) }}</div>
                                    @if ($obligation->attachments->isNotEmpty())
                                        <div class="text-xs text-sky-700 dark:text-sky-300">{{ __('Docs attached') }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $obligation->due_date->format('d-m-Y') }}</td>
                                <td class="px-3 py-2">{{ $obligation->provider ?: '—' }}</td>
                                <td class="px-3 py-2">{{ $obligation->reference ?: '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ $this->formatCurrency($obligation->amount) }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $this->obligationStatusClasses($obligation) }}">{{ $this->obligationStatusLabel($obligation) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>
</section>
