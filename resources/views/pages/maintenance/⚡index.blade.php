<?php

use App\Concerns\FormatsAttachmentUploadErrors;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\LedgerEntry;
use App\Models\MaintenanceRecord;
use App\Support\AttachmentManager;
use App\Support\CurrencyFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use FormatsAttachmentUploadErrors;
    use WithFileUploads;

    public bool $showForm = false;
    public ?int $editingMaintenanceId = null;
    public array $newAttachments = [];

    /**
     * @var array<string, string>
     */
    public array $serviceTypeOptions = [
        'oil_change' => 'Oil Change',
        'tire_rotation' => 'Tire Rotation',
        'brake_service' => 'Brake Service',
        'battery_service' => 'Battery Service',
        'air_filter' => 'Air Filter',
        'inspection' => 'Inspection',
        'other' => 'Other',
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
        $this->editingMaintenanceId = null;
        $this->resetForm();

        if ($this->cars->isNotEmpty()) {
            $this->form['car_id'] = (string) $this->cars->first()->id;
        }

        $this->showForm = true;
    }

    public function editRecord(int $recordId): void
    {
        $record = Auth::user()->maintenanceRecords()->with('ledgerEntry')->findOrFail($recordId);

        $this->editingMaintenanceId = $record->id;
        $this->form = [
            'car_id' => (string) $record->car_id,
            'service_type_option' => array_key_exists($record->service_type, $this->serviceTypeOptions) ? $record->service_type : 'other',
            'service_type_custom' => array_key_exists($record->service_type, $this->serviceTypeOptions) ? '' : $record->service_type,
            'provider' => $record->provider ?? '',
            'service_date' => $record->service_date->format('Y-m-d'),
            'odometer' => $record->odometer ?? '',
            'cost' => $record->ledgerEntry !== null ? (string) $record->ledgerEntry->amount : '',
            'notes' => $record->notes ?? '',
            'next_due_date' => $record->next_due_date?->format('Y-m-d') ?? '',
            'next_due_odometer' => $record->next_due_odometer ?? '',
        ];

        $this->showForm = true;
    }

    public function saveRecord(): void
    {
        $validated = $this->validate(
            array_merge($this->maintenanceRules(), $this->attachmentRules()),
            array_merge($this->maintenanceMessages(), $this->attachmentMessages()),
        );
        $form = $validated['form'];

        if ($form['service_type_option'] === 'other' && trim((string) ($form['service_type_custom'] ?? '')) === '') {
            $this->addError('form.service_type_custom', 'Custom service type is required.');

            return;
        }

        $normalized = $this->normalizeMaintenanceAttributes($form);
        $attributes = $normalized['attributes'];
        $amount = $normalized['amount'];

        DB::transaction(function () use ($attributes, $amount): void {
            if ($this->editingMaintenanceId !== null) {
                $record = Auth::user()->maintenanceRecords()->findOrFail($this->editingMaintenanceId);
                $record->update($attributes);
            } else {
                $record = Auth::user()->maintenanceRecords()->create($attributes);
            }

            $this->syncMaintenanceLedgerEntry($record, $amount);
            app(AttachmentManager::class)->storeMany($record, $this->newAttachments);
        });

        $this->cancelForm();
        $this->dispatch('maintenance-saved');
    }

    public function deleteRecord(int $recordId): void
    {
        DB::transaction(function () use ($recordId): void {
            $record = Auth::user()->maintenanceRecords()->findOrFail($recordId);

            if ($record->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($record->ledger_entry_id)->delete();
            }

            $record->delete();
        });
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingMaintenanceId = null;
        $this->newAttachments = [];
        $this->resetForm();
    }

    public function deleteAttachment(int $attachmentId): void
    {
        if ($this->editingMaintenanceId === null) {
            return;
        }

        $record = Auth::user()->maintenanceRecords()->with('attachments')->findOrFail($this->editingMaintenanceId);
        $attachment = $record->attachments()->findOrFail($attachmentId);

        app(AttachmentManager::class)->delete($attachment);

        unset($this->editingAttachments);
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
    public function maintenanceRecords(): Collection
    {
        return Auth::user()->maintenanceRecords()
            ->with(['car', 'ledgerEntry', 'attachments'])
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function reminderStats(): array
    {
        $overdue = 0;
        $dueSoon = 0;

        foreach ($this->maintenanceRecords as $record) {
            $status = $this->recordStatus($record);

            if ($status === 'overdue') {
                $overdue++;
            } elseif ($status === 'due_soon') {
                $dueSoon++;
            }
        }

        return [
            'overdue' => $overdue,
            'due_soon' => $dueSoon,
        ];
    }

    #[Computed]
    public function editingAttachments(): Collection
    {
        if ($this->editingMaintenanceId === null) {
            return new Collection();
        }

        return Auth::user()
            ->maintenanceRecords()
            ->with('attachments')
            ->findOrFail($this->editingMaintenanceId)
            ->attachments
            ->sortByDesc('id')
            ->values();
    }

    public function recordStatus(MaintenanceRecord $record): string
    {
        $currentOdometer = $record->car?->current_odometer;

        $isDateOverdue = $record->next_due_date !== null && $record->next_due_date->isPast();
        $isDateSoon = $record->next_due_date !== null && $record->next_due_date->isFuture() && $record->next_due_date->lte(now()->addDays(14));

        $isOdometerOverdue = $record->next_due_odometer !== null
            && $currentOdometer !== null
            && $currentOdometer >= $record->next_due_odometer;

        $isOdometerSoon = $record->next_due_odometer !== null
            && $currentOdometer !== null
            && $currentOdometer < $record->next_due_odometer
            && $currentOdometer >= ($record->next_due_odometer - 500);

        if ($isDateOverdue || $isOdometerOverdue) {
            return 'overdue';
        }

        if ($isDateSoon || $isOdometerSoon) {
            return 'due_soon';
        }

        return 'upcoming';
    }

    /**
     * @return array<string, mixed>
     */
    protected function maintenanceRules(): array
    {
        return [
            'form.car_id' => [
                'required',
                'integer',
                Rule::exists('cars', 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
            'form.service_type_option' => ['required', Rule::in(array_keys($this->serviceTypeOptions))],
            'form.service_type_custom' => ['nullable', 'string', 'max:255'],
            'form.provider' => ['nullable', 'string', 'max:255'],
            'form.service_date' => ['required', 'date'],
            'form.odometer' => ['nullable', 'integer', 'min:0'],
            'form.cost' => ['nullable', 'numeric', 'min:0'],
            'form.notes' => ['nullable', 'string'],
            'form.next_due_date' => ['nullable', 'date'],
            'form.next_due_odometer' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function maintenanceMessages(): array
    {
        return [
            'form.car_id.required' => 'Please select a car.',
            'form.service_type.required' => 'Service type is required.',
            'form.service_date.required' => 'Service date is required.',
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
    protected function normalizeMaintenanceAttributes(array $form): array
    {
        foreach (['provider', 'service_type_custom', 'odometer', 'cost', 'notes', 'next_due_date', 'next_due_odometer'] as $field) {
            if ($form[$field] === '') {
                $form[$field] = null;
            }
        }

        $serviceType = $form['service_type_option'] === 'other'
            ? (string) ($form['service_type_custom'] ?? '')
            : (string) $form['service_type_option'];

        if ($serviceType === '') {
            $serviceType = 'other';
        }

        $amount = $form['cost'] !== null ? (float) $form['cost'] : null;

        return [
            'attributes' => [
                'car_id' => (int) $form['car_id'],
                'service_type' => $serviceType,
                'provider' => $form['provider'],
                'service_date' => $form['service_date'],
                'odometer' => $form['odometer'] !== null ? (int) $form['odometer'] : null,
                'notes' => $form['notes'],
                'next_due_date' => $form['next_due_date'],
                'next_due_odometer' => $form['next_due_odometer'] !== null ? (int) $form['next_due_odometer'] : null,
            ],
            'amount' => $amount,
        ];
    }

    protected function syncMaintenanceLedgerEntry(MaintenanceRecord $record, ?float $amount): void
    {
        if ($amount === null || $amount <= 0) {
            if ($record->ledger_entry_id !== null) {
                Auth::user()->ledgerEntries()->whereKey($record->ledger_entry_id)->delete();
                $record->update(['ledger_entry_id' => null]);
            }

            return;
        }

        $account = Account::query()->firstOrCreate(
            ['key' => 'maintenance_expense'],
            [
                'user_id' => null,
                'name' => 'Maintenance',
                'group' => 'expense',
                'is_system' => true,
                'is_active' => true,
            ],
        );

        $entryAttributes = [
            'user_id' => Auth::id(),
            'car_id' => $record->car_id,
            'account_id' => $account->id,
            'entry_date' => $record->service_date->format('Y-m-d'),
            'entry_type' => 'expense',
            'amount' => $amount,
            'source_type' => 'maintenance_record',
            'source_id' => $record->id,
            'reference' => $record->provider,
            'notes' => $record->notes,
        ];

        $entry = $record->ledger_entry_id !== null
            ? Auth::user()->ledgerEntries()->findOrFail($record->ledger_entry_id)
            : new LedgerEntry();

        $entry->fill($entryAttributes);
        $entry->save();

        $updates = [];

        if ($record->ledger_entry_id !== $entry->id) {
            $updates['ledger_entry_id'] = $entry->id;
        }

        if ($updates !== []) {
            $record->update($updates);
        }
    }

    protected function resetForm(): void
    {
        $this->form = [
            'car_id' => '',
            'service_type_option' => 'oil_change',
            'service_type_custom' => '',
            'provider' => '',
            'service_date' => now()->format('Y-m-d'),
            'odometer' => '',
            'cost' => '',
            'notes' => '',
            'next_due_date' => '',
            'next_due_odometer' => '',
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Maintenance') }}</flux:heading>
            <flux:subheading>{{ __('Log servicing and track upcoming due reminders.') }}</flux:subheading>
        </div>

        <flux:button class="w-full sm:w-auto" variant="primary" wire:click="startCreating" :disabled="$this->cars->isEmpty()">
            {{ __('Add Record') }}
        </flux:button>
    </div>

    @if ($this->cars->isEmpty())
        <flux:card>
            <flux:text>{{ __('Add a car first before creating maintenance records.') }}</flux:text>
        </flux:card>
    @endif

    <flux:card class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                <flux:text>{{ __('Overdue') }}</flux:text>
                <flux:heading size="lg">{{ $this->reminderStats['overdue'] }}</flux:heading>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                <flux:text>{{ __('Due Soon') }}</flux:text>
                <flux:heading size="lg">{{ $this->reminderStats['due_soon'] }}</flux:heading>
            </div>
        </div>
        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Tap a record to edit it.') }}</flux:text>
    </flux:card>

    <flux:modal :closable="false" wire:model="showForm" class="max-h-[90vh] overflow-y-auto border border-zinc-300 shadow-2xl ring-1 ring-black/10 md:w-[48rem] dark:border-zinc-600 dark:ring-white/10">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading>{{ $editingMaintenanceId ? __('Edit Maintenance') : __('Add Maintenance') }}</flux:heading>
                    <flux:subheading>{{ __('Add service details and optional due reminders.') }}</flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" wire:click="cancelForm">{{ __('Close') }}</flux:button>
            </div>

            <form wire:submit="saveRecord" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:select wire:model="form.car_id" :label="__('Car')" required>
                        <flux:select.option value="">{{ __('Select car') }}</flux:select.option>
                        @foreach ($this->cars as $car)
                            <flux:select.option :value="$car->id">
                                {{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="form.service_type_option" :label="__('Service Type')" required>
                        @foreach ($serviceTypeOptions as $value => $label)
                            <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if (($form['service_type_option'] ?? null) === 'other')
                        <flux:input wire:model="form.service_type_custom" :label="__('Custom Service Type')" type="text" required />
                    @endif

                    <flux:input wire:model="form.provider" :label="__('Provider/Shop')" type="text" />
                    <flux:input wire:model="form.service_date" :label="__('Service Date')" type="date" required />
                    <flux:input wire:model="form.odometer" :label="__('Odometer')" type="number" min="0" step="1" />
                    <flux:input wire:model="form.cost" :label="__('Cost')" type="number" min="0" step="0.01" />
                    <flux:input wire:model="form.next_due_date" :label="__('Next Due Date')" type="date" />
                    <flux:input wire:model="form.next_due_odometer" :label="__('Next Due Odometer')" type="number" min="0" step="1" />
                </div>

                <flux:input wire:model="form.notes" :label="__('Notes')" type="text" />

                <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <div>
                        <flux:heading size="sm">{{ __('Attachments') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add invoices, service sheets, or receipt photos.') }}</flux:text>
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

                        @if ($editingMaintenanceId !== null)
                            @if ($this->editingAttachments->isEmpty())
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No saved attachments yet. Any selected files will be attached after you press Save Record.') }}</flux:text>
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

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="newAttachments">
                        <span wire:loading.remove wire:target="newAttachments">{{ __('Save Record') }}</span>
                        <span wire:loading wire:target="newAttachments">{{ __('Upload in progress...') }}</span>
                    </flux:button>

                    <x-action-message on="maintenance-saved">
                        {{ __('Saved.') }}
                    </x-action-message>
                </div>
            </form>
        </div>
    </flux:modal>

    @if ($this->maintenanceRecords->isEmpty())
        <flux:card>
            <flux:text>{{ __('No maintenance records found for the current filter.') }}</flux:text>
        </flux:card>
    @else
        <div class="space-y-3">
            @foreach ($this->maintenanceRecords as $record)
                @php($status = $this->recordStatus($record))

                <flux:card
                    class="cursor-pointer space-y-3 border border-zinc-200 bg-zinc-50/70 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                    wire:click="editRecord({{ $record->id }})"
                    wire:key="maintenance-card-{{ $record->id }}"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading>{{ $record->service_type }}</flux:heading>
                            <flux:subheading>
                                {{ $record->service_date->format('d-m-Y') }}
                                ·
                                {{ trim(collect([$record->car->year, $record->car->make, $record->car->model])->filter()->implode(' ')) }}
                            </flux:subheading>
                            @if ($record->attachments->isNotEmpty())
                                <flux:text class="text-xs text-sky-700 dark:text-sky-300">{{ __('Docs attached') }}</flux:text>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($status === 'overdue')
                                <flux:badge color="red">{{ __('Overdue') }}</flux:badge>
                            @elseif ($status === 'due_soon')
                                <flux:badge color="yellow">{{ __('Due Soon') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-2 text-sm md:grid-cols-3">
                        <flux:text>{{ __('Provider') }}: {{ $record->provider ?: __('N/A') }}</flux:text>
                        <flux:text>{{ __('Cost') }}: {{ $record->ledgerEntry !== null ? $this->formatCurrency($record->ledgerEntry->amount) : __('N/A') }}</flux:text>
                        <flux:text>{{ __('Odometer') }}: {{ $record->odometer ?? __('N/A') }}</flux:text>
                        <flux:text>{{ __('Next Due Date') }}: {{ $record->next_due_date?->format('d-m-Y') ?: __('N/A') }}</flux:text>
                        <flux:text>{{ __('Next Due Odometer') }}: {{ $record->next_due_odometer ?? __('N/A') }}</flux:text>
                        @if ($record->attachments->isNotEmpty())
                            <flux:text class="text-sky-700 dark:text-sky-300">{{ __('Docs attached') }}</flux:text>
                        @endif
                    </div>

                    @if ($record->notes)
                        <flux:text>{{ $record->notes }}</flux:text>
                    @endif

                    @if ($record->attachments->isNotEmpty())
                        <div class="space-y-2 rounded-xl border border-sky-200 bg-sky-50/70 p-3 dark:border-sky-900/60 dark:bg-sky-950/20">
                            <flux:text class="text-xs font-medium uppercase tracking-wide text-sky-700 dark:text-sky-300">
                                {{ trans_choice('{1} 1 attachment|[2,*] :count attachments', $record->attachments->count(), ['count' => $record->attachments->count()]) }}
                            </flux:text>

                            <div class="flex flex-wrap gap-2">
                                @foreach ($record->attachments as $attachment)
                                    <a
                                        href="{{ route('attachments.show', $attachment) }}"
                                        target="_blank"
                                        rel="noreferrer"
                                        onclick="event.stopPropagation()"
                                        class="inline-flex max-w-full items-center gap-2 rounded-lg border border-sky-300 bg-white px-3 py-1.5 text-sm font-medium text-sky-800 transition hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/30 dark:text-sky-100 dark:hover:bg-sky-950/50"
                                    >
                                        <span class="truncate">{{ $attachment->original_name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</section>
