<x-layouts::app :title="__('Dashboard')">
    <div
        class="w-full space-y-6"
        x-data="{
            activeTab: @js(request()->has('transaction_type') || request()->has('period') || request()->has('page') ? 'ledger' : 'overview'),
            showServiceModal: false,
            showRecurringModal: false,
            selectedService: null,
            selectedRecurring: null,
            maintenanceUpdateUrl: @js(route('dashboard.maintenance.update', ['maintenanceRecord' => '__ID__'])),
            maintenanceDeleteUrl: @js(route('dashboard.maintenance.delete', ['maintenanceRecord' => '__ID__'])),
            recurringUpdateUrl: @js(route('dashboard.recurring.update', ['recurringTransaction' => '__ID__'])),
            recurringDeleteUrl: @js(route('dashboard.recurring.delete', ['recurringTransaction' => '__ID__']))
        }"
    >
        <div>
            <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:subheading>{{ __('Running totals and all transactions in one view.') }}</flux:subheading>
            <flux:text class="mt-2">
                {{ __('Current Car') }}:
                <strong>
                    @if ($currentCar)
                        {{ trim(collect([$currentCar->year, $currentCar->make, $currentCar->model])->filter()->implode(' ')) }}
                    @else
                        {{ __('No active car selected') }}
                    @endif
                </strong>
            </flux:text>
        </div>

        <flux:card class="p-2">
            <div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('Dashboard Sections') }}">
                <button
                    type="button"
                    role="tab"
                    x-on:click="activeTab = 'overview'"
                    x-bind:aria-selected="activeTab === 'overview'"
                    x-bind:class="activeTab === 'overview' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                >
                    {{ __('Overview') }}
                </button>
                <button
                    type="button"
                    role="tab"
                    x-on:click="activeTab = 'ledger'"
                    x-bind:aria-selected="activeTab === 'ledger'"
                    x-bind:class="activeTab === 'ledger' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                >
                    {{ __('Ledger') }}
                </button>
            </div>
        </flux:card>

        <div x-show="activeTab === 'overview'" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-3">
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Net Cost (All-Time)') }}</flux:text>
                    <flux:heading class="{{ $allTimeNetCost < 0 ? 'text-emerald-600 dark:text-emerald-400' : ($allTimeNetCost > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                        {{ \App\Support\CurrencyFormatter::format($allTimeNetCost, $currencyCode) }}
                    </flux:heading>
                </flux:card>

                <flux:card class="space-y-1">
                    <flux:text>{{ __('Net Cost (This Month)') }}</flux:text>
                    <flux:heading class="{{ $monthNetCost < 0 ? 'text-emerald-600 dark:text-emerald-400' : ($monthNetCost > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                        {{ \App\Support\CurrencyFormatter::format($monthNetCost, $currencyCode) }}
                    </flux:heading>
                </flux:card>

                <flux:card class="space-y-1">
                    <flux:text>{{ __('Projected Year-End Net Cost') }}</flux:text>
                    <flux:heading class="{{ $projectedYearNetCost < 0 ? 'text-emerald-600 dark:text-emerald-400' : ($projectedYearNetCost > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                        {{ \App\Support\CurrencyFormatter::format($projectedYearNetCost, $currencyCode) }}
                    </flux:heading>
                </flux:card>
            </div>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Financial Summary') }}</flux:heading>
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Expenses are shown in red, reimbursements in green, and net values are green when in surplus (negative).') }}
                </flux:text>
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('Metric') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('All-Time') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('Actual YTD') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('Projected Remaining') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('Projected Year-End') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <td class="px-3 py-2 font-medium text-rose-700 dark:text-rose-400">{{ __('Expenses') }}</td>
                                <td class="px-3 py-2 text-right text-rose-700 dark:text-rose-400">{{ \App\Support\CurrencyFormatter::format($allTimeExpenses, $currencyCode) }}</td>
                                <td class="px-3 py-2 text-right text-rose-700 dark:text-rose-400">{{ \App\Support\CurrencyFormatter::format($actualYearExpenses, $currencyCode) }}</td>
                                <td class="px-3 py-2 text-right text-rose-700 dark:text-rose-400">{{ \App\Support\CurrencyFormatter::format($projectedRemainingExpenses, $currencyCode) }}</td>
                                <td class="px-3 py-2 text-right text-rose-700 dark:text-rose-400">{{ \App\Support\CurrencyFormatter::format($projectedYearExpenses, $currencyCode) }}</td>
                            </tr>
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <td class="px-3 py-2 font-medium text-emerald-700 dark:text-emerald-400">{{ __('Reimbursements') }}</td>
                                <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">{{ \App\Support\CurrencyFormatter::format($allTimeReimbursements, $currencyCode) }}</td>
                                <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">{{ \App\Support\CurrencyFormatter::format($actualYearReimbursements, $currencyCode) }}</td>
                                <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">{{ \App\Support\CurrencyFormatter::format($projectedRemainingReimbursements, $currencyCode) }}</td>
                                <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">{{ \App\Support\CurrencyFormatter::format($projectedYearReimbursements, $currencyCode) }}</td>
                            </tr>
                            <tr class="border-t border-zinc-200 font-medium dark:border-zinc-700">
                                <td class="px-3 py-2">{{ __('Net Cost') }}</td>
                                <td class="px-3 py-2 text-right {{ $allTimeNetCost < 0 ? 'text-emerald-700 dark:text-emerald-400' : ($allTimeNetCost > 0 ? 'text-rose-700 dark:text-rose-400' : '') }}">{{ \App\Support\CurrencyFormatter::format($allTimeNetCost, $currencyCode) }}</td>
                                <td class="px-3 py-2 text-right {{ $actualYearNetCost < 0 ? 'text-emerald-700 dark:text-emerald-400' : ($actualYearNetCost > 0 ? 'text-rose-700 dark:text-rose-400' : '') }}">{{ \App\Support\CurrencyFormatter::format($actualYearNetCost, $currencyCode) }}</td>
                                <td class="px-3 py-2 text-right {{ $projectedRemainingNetCost < 0 ? 'text-emerald-700 dark:text-emerald-400' : ($projectedRemainingNetCost > 0 ? 'text-rose-700 dark:text-rose-400' : '') }}">{{ \App\Support\CurrencyFormatter::format($projectedRemainingNetCost, $currencyCode) }}</td>
                                <td class="px-3 py-2 text-right {{ $projectedYearNetCost < 0 ? 'text-emerald-700 dark:text-emerald-400' : ($projectedYearNetCost > 0 ? 'text-rose-700 dark:text-rose-400' : '') }}">{{ \App\Support\CurrencyFormatter::format($projectedYearNetCost, $currencyCode) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </flux:card>

            <div class="grid gap-4 lg:grid-cols-2">
                <flux:card class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading>{{ __('Service Due (Next 14 Days)') }}</flux:heading>
                        <flux:badge>{{ $upcomingMaintenanceCount }}</flux:badge>
                    </div>

                    @if ($upcomingMaintenance->isEmpty())
                        <flux:text>{{ __('No service due in the next 14 days.') }}</flux:text>
                    @else
                        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="w-full min-w-[520px] text-left text-sm">
                                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">{{ __('Due Date') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Service') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Odometer') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Trigger') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Car') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($upcomingMaintenance as $record)
                                        @php
                                            $currentOdometer = $record->car?->current_odometer;
                                            $dateTriggered = $record->next_due_date !== null
                                                && $record->next_due_date->gte(now()->startOfDay())
                                                && $record->next_due_date->lte(now()->addDays(14)->endOfDay());
                                            $odometerTriggered = $record->next_due_odometer !== null
                                                && $currentOdometer !== null
                                                && $currentOdometer >= ((int) $record->next_due_odometer - 500);
                                            $triggerLabel = $dateTriggered && $odometerTriggered
                                                ? __('Date + Odometer')
                                                : ($dateTriggered ? __('Date') : __('Odometer'));
                                            $servicePayload = [
                                                'id' => $record->id,
                                                'service_type' => (string) $record->service_type,
                                                'next_due_date' => $record->next_due_date?->format('Y-m-d'),
                                                'next_due_date_display' => $record->next_due_date?->format('d-m-Y') ?? __('N/A'),
                                                'next_due_odometer' => $record->next_due_odometer,
                                                'current_odometer' => $currentOdometer,
                                                'trigger' => $triggerLabel,
                                                'car' => trim(collect([$record->car?->year, $record->car?->make, $record->car?->model])->filter()->implode(' ')) ?: __('N/A'),
                                                'notes' => $record->notes ?? '',
                                            ];
                                        @endphp
                                        <tr
                                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                                            tabindex="0"
                                            x-on:click='selectedService = @json($servicePayload); showServiceModal = true'
                                            x-on:keydown.enter='selectedService = @json($servicePayload); showServiceModal = true'
                                        >
                                            <td class="px-3 py-2">{{ $record->next_due_date?->format('d-m-Y') }}</td>
                                            <td class="px-3 py-2">{{ $record->service_type }}</td>
                                            <td class="px-3 py-2">
                                                @if ($record->next_due_odometer !== null)
                                                    {{ number_format((int) $currentOdometer) }}/{{ number_format((int) $record->next_due_odometer) }}
                                                @else
                                                    {{ __('N/A') }}
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">{{ $triggerLabel }}</td>
                                            <td class="px-3 py-2">
                                                {{ trim(collect([$record->car?->year, $record->car?->make, $record->car?->model])->filter()->implode(' ')) ?: __('N/A') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </flux:card>

                <flux:card class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading>{{ __('Recurring Due (Next 14 Days)') }}</flux:heading>
                        <flux:badge>{{ $upcomingRecurringCount }}</flux:badge>
                    </div>

                    @if ($upcomingRecurringTransactions->isEmpty())
                        <flux:text>{{ __('No recurring transactions due in the next 14 days.') }}</flux:text>
                    @else
                        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="w-full min-w-[520px] text-left text-sm">
                                <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">{{ __('Due Date') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Type') }}</th>
                                        <th class="px-3 py-2 font-medium">{{ __('Account') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($upcomingRecurringTransactions as $schedule)
                                        @php
                                            $recurringPayload = [
                                                'id' => $schedule->id,
                                                'next_entry_date' => $schedule->next_entry_date->format('Y-m-d'),
                                                'next_entry_date_display' => $schedule->next_entry_date->format('d-m-Y'),
                                                'entry_type' => (string) $schedule->entry_type,
                                                'account' => (string) ($schedule->account?->name ?? __('N/A')),
                                                'amount' => (string) $schedule->amount,
                                                'cadence' => (string) $schedule->cadence,
                                                'end_date' => $schedule->end_date?->format('Y-m-d'),
                                                'reference' => $schedule->reference ?? '',
                                                'notes' => $schedule->notes ?? '',
                                                'is_active' => (bool) $schedule->is_active,
                                            ];
                                        @endphp
                                        <tr
                                            class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                                            tabindex="0"
                                            x-on:click='selectedRecurring = @json($recurringPayload); showRecurringModal = true'
                                            x-on:keydown.enter='selectedRecurring = @json($recurringPayload); showRecurringModal = true'
                                        >
                                            <td class="px-3 py-2">{{ $schedule->next_entry_date->format('d-m-Y') }}</td>
                                            <td class="px-3 py-2">{{ $schedule->entry_type === 'income' ? __('Reimbursement') : __('Expense') }}</td>
                                            <td class="px-3 py-2">{{ $schedule->account?->name ?? __('N/A') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </flux:card>
            </div>
        </div>

        <div
            x-show="showServiceModal && selectedService"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            x-on:click.self="showServiceModal = false"
            x-on:keydown.escape.window="showServiceModal = false"
        >
            <div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading>{{ __('Service Reminder') }}</flux:heading>
                        <flux:subheading x-text="selectedService?.car"></flux:subheading>
                    </div>
                    <flux:button variant="ghost" x-on:click="showServiceModal = false">{{ __('Close') }}</flux:button>
                </div>

                <form id="service-edit-form" class="mt-4 space-y-3" method="POST" x-bind:action="maintenanceUpdateUrl.replace('__ID__', selectedService.id)">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-3 md:grid-cols-2">
                        <flux:input :label="__('Service')" type="text" x-bind:value="selectedService?.service_type ?? ''" disabled />
                        <flux:input :label="__('Trigger')" type="text" x-bind:value="selectedService?.trigger ?? ''" disabled />
                        <flux:input :label="__('Next Due Date')" name="next_due_date" type="date" x-model="selectedService.next_due_date" />
                        <flux:input :label="__('Next Due Odometer')" name="next_due_odometer" type="number" min="0" step="1" x-model="selectedService.next_due_odometer" />
                    </div>
                    <flux:input :label="__('Notes')" name="notes" type="text" x-model="selectedService.notes" />

                </form>

                <div class="mt-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <flux:button type="submit" form="service-edit-form" variant="primary">{{ __('Save') }}</flux:button>
                    </div>

                    <form method="POST" x-bind:action="maintenanceDeleteUrl.replace('__ID__', selectedService.id)" onsubmit="return confirm('Delete this maintenance reminder record?');">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
                    </form>
                </div>
            </div>
        </div>

        <div
            x-show="showRecurringModal && selectedRecurring"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            x-on:click.self="showRecurringModal = false"
            x-on:keydown.escape.window="showRecurringModal = false"
        >
            <div class="w-full max-w-xl rounded-xl bg-white p-5 shadow-xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading>{{ __('Recurring Reminder') }}</flux:heading>
                        <flux:subheading x-text="selectedRecurring?.account"></flux:subheading>
                    </div>
                    <flux:button variant="ghost" x-on:click="showRecurringModal = false">{{ __('Close') }}</flux:button>
                </div>

                <form id="recurring-edit-form" class="mt-4 space-y-3" method="POST" x-bind:action="recurringUpdateUrl.replace('__ID__', selectedRecurring.id)">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-3 md:grid-cols-2">
                        <flux:input :label="__('Type')" type="text" x-bind:value="selectedRecurring?.entry_type === 'income' ? '{{ __('Reimbursement') }}' : '{{ __('Expense') }}'" disabled />
                        <flux:input :label="__('Next Entry Date')" name="next_entry_date" type="date" x-model="selectedRecurring.next_entry_date" required />
                        <flux:input :label="__('Amount')" name="amount" type="number" min="0.01" step="0.01" x-model="selectedRecurring.amount" required />
                        <flux:select :label="__('Cadence')" name="cadence" x-model="selectedRecurring.cadence">
                            <flux:select.option value="monthly">{{ __('Monthly') }}</flux:select.option>
                            <flux:select.option value="quarterly">{{ __('Quarterly') }}</flux:select.option>
                            <flux:select.option value="yearly">{{ __('Yearly') }}</flux:select.option>
                        </flux:select>
                        <flux:input :label="__('End Date')" name="end_date" type="date" x-model="selectedRecurring.end_date" />
                        <flux:input :label="__('Reference')" name="reference" type="text" x-model="selectedRecurring.reference" />
                    </div>
                    <flux:input :label="__('Notes')" name="notes" type="text" x-model="selectedRecurring.notes" />
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="selectedRecurring.is_active">
                        <span>{{ __('Active') }}</span>
                    </label>

                </form>

                <div class="mt-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <flux:button type="submit" form="recurring-edit-form" variant="primary">{{ __('Save') }}</flux:button>
                    </div>

                    <form method="POST" x-bind:action="recurringDeleteUrl.replace('__ID__', selectedRecurring.id)" onsubmit="return confirm('Delete this recurring schedule?');">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="activeTab === 'ledger'" class="space-y-6">
            <flux:card class="space-y-4" x-data="{ showEntryModal: false, selectedEntry: null }">
                <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-3">
                    <div class="w-full sm:w-64">
                        <flux:label for="transaction_type">{{ __('Transaction Type') }}</flux:label>
                        <select
                            id="transaction_type"
                            name="transaction_type"
                            onchange="this.form.submit()"
                            class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            @foreach ($transactionTypeOptions as $value => $label)
                                <option value="{{ $value }}" @selected($selectedTransactionType === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full sm:w-56">
                        <flux:label for="period">{{ __('Period') }}</flux:label>
                        <select
                            id="period"
                            name="period"
                            onchange="this.form.submit()"
                            class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            @foreach ($periodOptions as $value => $label)
                                <option value="{{ $value }}" @selected($selectedPeriod === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                @if ($transactions->count() === 0)
                    <flux:text>{{ __('No transactions found for the selected filter.') }}</flux:text>
                @else
                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Date') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Type') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Account') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Description') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Expense') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Income') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transactions as $entry)
                                    @php
                                        $typeLabel = match ($entry->source_type) {
                                            'fuel_log' => 'Fuel',
                                            'maintenance_record' => 'Maintenance',
                                            'reimbursement' => 'Reimbursement',
                                            'expense' => 'Manual Expense',
                                            'recurring' => 'Recurring',
                                            default => ucfirst((string) $entry->source_type),
                                        };
                                        $description = $entry->reference ?: ($entry->notes ?: '');
                                        $entrySummary = [
                                            'date' => $entry->entry_date->format('d-m-Y'),
                                            'type' => __($typeLabel),
                                            'account' => $entry->account?->name ?? __('N/A'),
                                            'description' => $description !== '' ? $description : __('N/A'),
                                            'expense' => $entry->entry_type === 'expense' ? \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) : '-',
                                            'income' => $entry->entry_type === 'income' ? \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) : '-',
                                            'entry_type' => $entry->entry_type,
                                            'notes' => $entry->notes ?: __('N/A'),
                                        ];
                                    @endphp

                                    <tr
                                        class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                                        tabindex="0"
                                        x-on:click='selectedEntry = @json($entrySummary); showEntryModal = true'
                                        x-on:keydown.enter='selectedEntry = @json($entrySummary); showEntryModal = true'
                                    >
                                        <td class="px-3 py-2">{{ $entry->entry_date->format('d-m-Y') }}</td>
                                        <td class="px-3 py-2">{{ __($typeLabel) }}</td>
                                        <td class="px-3 py-2">{{ $entry->account?->name ?? __('N/A') }}</td>
                                        <td class="px-3 py-2">{{ $description !== '' ? $description : __('N/A') }}</td>
                                        <td class="px-3 py-2 text-right">
                                            {{ $entry->entry_type === 'expense' ? \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) : '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            {{ $entry->entry_type === 'income' ? \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $transactions->links() }}
                    </div>
                @endif

                <div
                    x-show="showEntryModal"
                    x-transition.opacity
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                    x-on:click.self="showEntryModal = false"
                    x-on:keydown.escape.window="showEntryModal = false"
                >
                    <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <flux:heading>{{ __('Transaction Summary') }}</flux:heading>
                                <flux:subheading x-text="selectedEntry?.date"></flux:subheading>
                            </div>
                            <flux:button variant="ghost" x-on:click="showEntryModal = false">{{ __('Close') }}</flux:button>
                        </div>

                        <div class="mt-4 grid gap-2 text-sm">
                            <div><strong>{{ __('Type') }}:</strong> <span x-text="selectedEntry?.type"></span></div>
                            <div><strong>{{ __('Account') }}:</strong> <span x-text="selectedEntry?.account"></span></div>
                            <div><strong>{{ __('Description') }}:</strong> <span x-text="selectedEntry?.description"></span></div>
                            <div><strong>{{ __('Expense') }}:</strong> <span x-text="selectedEntry?.expense"></span></div>
                            <div><strong>{{ __('Income') }}:</strong> <span x-text="selectedEntry?.income"></span></div>
                            <div><strong>{{ __('Notes') }}:</strong> <span x-text="selectedEntry?.notes"></span></div>
                        </div>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</x-layouts::app>
