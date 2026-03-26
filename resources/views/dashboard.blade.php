<x-layouts::app :title="__('Dashboard')">
    <div
        class="w-full space-y-6"
        x-data="{
            activeTab: @js(request()->has('transaction_type') || request()->has('period') || request()->has('page') ? 'ledger' : 'overview'),
            showQuickActionModal: false,
            showServiceModal: false,
            showRecurringModal: false,
            selectedQuickAction: null,
            selectedService: null,
            selectedRecurring: null,
            maintenanceUpdateUrl: @js(route('dashboard.maintenance.update', ['maintenanceRecord' => '__ID__'])),
            maintenanceDeleteUrl: @js(route('dashboard.maintenance.delete', ['maintenanceRecord' => '__ID__'])),
            recurringUpdateUrl: @js(route('dashboard.recurring.update', ['recurringTransaction' => '__ID__'])),
            recurringDeleteUrl: @js(route('dashboard.recurring.delete', ['recurringTransaction' => '__ID__'])),
            openQuickAction(payload) {
                this.selectedQuickAction = payload;
                this.showQuickActionModal = true;
            }
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
            @if ($quickActions->isNotEmpty())
                <flux:card class="space-y-4">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <div>
                            <flux:heading>{{ __('Quick Actions') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Tap once to capture a common entry quickly.') }}</flux:text>
                        </div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Configure these in the Quick Actions page.') }}</flux:text>
                    </div>

                    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                        @foreach ($quickActions as $quickAction)
                            @php
                                $mileageCarId = $quickAction->car_id ?? $currentCar?->id;
                                $defaultOdometer = $mileageCarId !== null ? ($latestOdometerByCar[$mileageCarId] ?? null) : null;
                                $quickActionPayload = [
                                    'name' => $quickAction->name,
                                    'entry_target' => $quickAction->entry_target,
                                    'amount' => (float) $quickAction->amount,
                                    'amount_display' => \App\Support\CurrencyFormatter::format($quickAction->amount, $currencyCode),
                                    'requires_amount' => $quickAction->entry_target !== 'mileage_log' && (float) $quickAction->amount <= 0,
                                    'amount_input' => (float) $quickAction->amount > 0 ? (string) $quickAction->amount : '',
                                    'fuel_volume' => $quickAction->fuel_volume !== null ? (float) $quickAction->fuel_volume : null,
                                    'fuel_volume_display' => $quickAction->fuel_volume !== null ? number_format((float) $quickAction->fuel_volume, 3).' '.(auth()->user()->volume_unit === 'litres' ? 'L' : 'gal') : __('N/A'),
                                    'requires_fuel_volume' => $quickAction->entry_target === 'fuel_log' && ((float) ($quickAction->fuel_volume ?? 0) <= 0),
                                    'fuel_volume_input' => $quickAction->fuel_volume !== null && (float) $quickAction->fuel_volume > 0 ? (string) $quickAction->fuel_volume : '',
                                    'fuel_full_tank' => (bool) $quickAction->fuel_full_tank,
                                    'mileage_locations' => $quickAction->mileage_locations ?? '',
                                    'mileage_distance' => (int) ($quickAction->mileage_distance ?? 0),
                                    'start_odometer_input' => (string) ($defaultOdometer ?? 0),
                                    'requires_mileage' => $quickAction->entry_target === 'mileage_log',
                                    'odometer_input' => (string) ($defaultOdometer ?? 0),
                                    'requires_user_input' => $quickAction->entry_target === 'mileage_log' || $quickAction->entry_target === 'fuel_log' || (float) $quickAction->amount <= 0 || ($quickAction->entry_target === 'fuel_log' && ((float) ($quickAction->fuel_volume ?? 0) <= 0)),
                                    'vendor' => $quickAction->vendor ?? __('N/A'),
                                    'car' => $quickAction->car ? trim(collect([$quickAction->car->year, $quickAction->car->make, $quickAction->car->model])->filter()->implode(' ')) : __('Default Car'),
                                    'notes' => $quickAction->notes ?: __('N/A'),
                                    'run_url' => route('dashboard.quick-actions.run', $quickAction),
                                ];
                            @endphp
                            <flux:button
                                type="button"
                                variant="primary"
                                class="h-14 justify-center rounded-xl px-4 text-center text-base font-medium"
                                x-on:click="openQuickAction({{ \Illuminate\Support\Js::from($quickActionPayload) }})"
                            >
                                {{ $quickAction->name }}
                            </flux:button>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            <flux:card class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <flux:heading>{{ __('Summary Snapshot') }}</flux:heading>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Financial headlines and current-car running costs in one view.') }}</flux:text>
                    </div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('All-time running costs are based on purchase to current odometer.') }}</flux:text>
                </div>

                <div class="grid gap-3 lg:grid-cols-[1.2fr_1fr]">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <flux:text class="font-medium">{{ __('Net Cost') }}</flux:text>
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Green indicates surplus / lower net cost.') }}</flux:text>
                        </div>
                        <dl class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-lg border border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-950/40">
                                <dt class="text-xs uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('This Month') }}</dt>
                                <dd class="mt-1 text-lg font-semibold {{ $monthNetCost < 0 ? 'text-emerald-600 dark:text-emerald-400' : ($monthNetCost > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                                    {{ \App\Support\CurrencyFormatter::format($monthNetCost, $currencyCode) }}
                                </dd>
                            </div>
                            <div class="rounded-lg border border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-950/40">
                                <dt class="text-xs uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Projected Year-End') }}</dt>
                                <dd class="mt-1 text-lg font-semibold {{ $projectedYearNetCost < 0 ? 'text-emerald-600 dark:text-emerald-400' : ($projectedYearNetCost > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                                    {{ \App\Support\CurrencyFormatter::format($projectedYearNetCost, $currencyCode) }}
                                </dd>
                            </div>
                            <div class="rounded-lg border border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-950/40">
                                <dt class="text-xs uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('All-Time') }}</dt>
                                <dd class="mt-1 text-lg font-semibold {{ $allTimeNetCost < 0 ? 'text-emerald-600 dark:text-emerald-400' : ($allTimeNetCost > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100') }}">
                                    {{ \App\Support\CurrencyFormatter::format($allTimeNetCost, $currencyCode) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <flux:text class="font-medium">{{ __('Current Car Ownership Metrics') }}</flux:text>
                            @if ($currentCar !== null)
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ trim(collect([$currentCar->year, $currentCar->make, $currentCar->model])->filter()->implode(' ')) }}</flux:text>
                            @endif
                        </div>

                        @if ($currentCar === null || $currentCarOwnershipMetrics === null)
                            <flux:text>{{ __('Set a current car to view ownership metrics.') }}</flux:text>
                        @else
                            <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <div class="rounded-lg border border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-950/40">
                                    <dt class="text-xs uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Distance Travelled') }}</dt>
                                    <dd class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $currentCarOwnershipMetrics['distance_display'] }}</dd>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-950/40">
                                    <dt class="text-xs uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Net Cost / '.$currentCarOwnershipMetrics['unit_label']) }}</dt>
                                    <dd class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $currentCarOwnershipMetrics['net_cost_per_distance_display'] }}</dd>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-950/40">
                                    <dt class="text-xs uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Fuel Cost / '.$currentCarOwnershipMetrics['unit_label']) }}</dt>
                                    <dd class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $currentCarOwnershipMetrics['fuel_cost_per_distance_display'] }}</dd>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-950/40">
                                    <dt class="text-xs uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Maintenance Cost / '.$currentCarOwnershipMetrics['unit_label']) }}</dt>
                                    <dd class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $currentCarOwnershipMetrics['maintenance_cost_per_distance_display'] }}</dd>
                                </div>
                                <div class="rounded-lg border border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-950/40 sm:col-span-2 xl:col-span-2">
                                    <dt class="text-xs uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Total Ownership Cost / '.$currentCarOwnershipMetrics['unit_label']) }}</dt>
                                    <dd class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $currentCarOwnershipMetrics['total_ownership_cost_per_distance_display'] }}</dd>
                                </div>
                            </dl>
                        @endif
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <flux:text class="font-medium">{{ __('Financial Summary') }}</flux:text>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Expenses are shown in red, reimbursements in green, and net values are green when in surplus (negative).') }}
                        </flux:text>
                    </div>

                    <div class="space-y-3 md:hidden">
                        @foreach ([
                            __('Expenses') => [
                                'all_time' => \App\Support\CurrencyFormatter::format($allTimeExpenses, $currencyCode),
                                'actual' => \App\Support\CurrencyFormatter::format($actualYearExpenses, $currencyCode),
                                'remaining' => \App\Support\CurrencyFormatter::format($projectedRemainingExpenses, $currencyCode),
                                'year_end' => \App\Support\CurrencyFormatter::format($projectedYearExpenses, $currencyCode),
                                'tone' => 'text-rose-700 dark:text-rose-400',
                            ],
                            __('Reimbursements') => [
                                'all_time' => \App\Support\CurrencyFormatter::format($allTimeReimbursements, $currencyCode),
                                'actual' => \App\Support\CurrencyFormatter::format($actualYearReimbursements, $currencyCode),
                                'remaining' => \App\Support\CurrencyFormatter::format($projectedRemainingReimbursements, $currencyCode),
                                'year_end' => \App\Support\CurrencyFormatter::format($projectedYearReimbursements, $currencyCode),
                                'tone' => 'text-emerald-700 dark:text-emerald-400',
                            ],
                            __('Net Cost') => [
                                'all_time' => \App\Support\CurrencyFormatter::format($allTimeNetCost, $currencyCode),
                                'actual' => \App\Support\CurrencyFormatter::format($actualYearNetCost, $currencyCode),
                                'remaining' => \App\Support\CurrencyFormatter::format($projectedRemainingNetCost, $currencyCode),
                                'year_end' => \App\Support\CurrencyFormatter::format($projectedYearNetCost, $currencyCode),
                                'tone' => $projectedYearNetCost < 0 ? 'text-emerald-700 dark:text-emerald-400' : ($projectedYearNetCost > 0 ? 'text-rose-700 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100'),
                            ],
                        ] as $label => $summaryRow)
                            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <flux:text class="font-medium">{{ $label }}</flux:text>
                                    <span class="text-sm font-semibold {{ $summaryRow['tone'] }}">{{ $summaryRow['year_end'] }}</span>
                                </div>
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('All-Time') }}</dt>
                                        <dd class="{{ $summaryRow['tone'] }}">{{ $summaryRow['all_time'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Actual YTD') }}</dt>
                                        <dd class="{{ $summaryRow['tone'] }}">{{ $summaryRow['actual'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Projected Remaining') }}</dt>
                                        <dd class="{{ $summaryRow['tone'] }}">{{ $summaryRow['remaining'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Projected Year-End') }}</dt>
                                        <dd class="{{ $summaryRow['tone'] }}">{{ $summaryRow['year_end'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full min-w-[860px] text-left text-sm tabular-nums">
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
                </div>
            </flux:card>

            <div class="grid gap-4 xl:grid-cols-3">
                <flux:card class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading>{{ __('Service Due (Next 14 Days)') }}</flux:heading>
                        <flux:badge>{{ $upcomingMaintenanceCount }}</flux:badge>
                    </div>

                    @if ($upcomingMaintenance->isEmpty())
                        <flux:text>{{ __('No service due in the next 14 days.') }}</flux:text>
                    @else
                        <div class="space-y-3">
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
                                <button
                                    type="button"
                                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                                    x-on:click='selectedService = @json($servicePayload); showServiceModal = true'
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-medium">{{ $record->service_type }}</div>
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ trim(collect([$record->car?->year, $record->car?->make, $record->car?->model])->filter()->implode(' ')) ?: __('N/A') }}</div>
                                        </div>
                                        <flux:badge>{{ $triggerLabel }}</flux:badge>
                                    </div>
                                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                        <div>
                                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Due Date') }}</dt>
                                            <dd>{{ $record->next_due_date?->format('d-m-Y') ?? __('N/A') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Odometer') }}</dt>
                                            <dd>
                                                @if ($record->next_due_odometer !== null)
                                                    {{ number_format((int) ($currentOdometer ?? 0)) }}/{{ number_format((int) $record->next_due_odometer) }}
                                                @else
                                                    {{ __('N/A') }}
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                </button>
                            @endforeach
                        </div>

                        <div class="pt-1">
                            <flux:button variant="ghost" :href="route('maintenance.index')" wire:navigate>{{ __('Manage Maintenance') }}</flux:button>
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
                        <div class="space-y-3">
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
                                <button
                                    type="button"
                                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                                    x-on:click='selectedRecurring = @json($recurringPayload); showRecurringModal = true'
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-medium">{{ $schedule->account?->name ?? __('N/A') }}</div>
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $schedule->entry_type === 'income' ? __('Reimbursement') : __('Expense') }}</div>
                                        </div>
                                        <flux:badge>{{ ucfirst((string) $schedule->cadence) }}</flux:badge>
                                    </div>
                                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                        <div>
                                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Due Date') }}</dt>
                                            <dd>{{ $schedule->next_entry_date->format('d-m-Y') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Amount') }}</dt>
                                            <dd>{{ \App\Support\CurrencyFormatter::format($schedule->amount, $currencyCode) }}</dd>
                                        </div>
                                    </dl>
                                </button>
                            @endforeach
                        </div>

                        <div class="pt-1">
                            <flux:button variant="ghost" :href="route('recurring.index')" wire:navigate>{{ __('Manage Recurring') }}</flux:button>
                        </div>
                    @endif
                </flux:card>

                <flux:card class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <flux:heading>{{ __('Obligations Due (Next 30 Days)') }}</flux:heading>
                        <flux:badge>{{ $upcomingObligationsCount }}</flux:badge>
                    </div>

                    @if ($upcomingObligations->isEmpty())
                        <flux:text>{{ __('No obligations due in the next 30 days.') }}</flux:text>
                    @else
                        <div class="space-y-3">
                            @foreach ($upcomingObligations as $obligation)
                                @php
                                    $statusLabel = \App\Support\VehicleObligationStatus::label($obligation);
                                    $statusClasses = match (\App\Support\VehicleObligationStatus::status($obligation)) {
                                        'overdue' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
                                        'due_soon' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
                                        default => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
                                    };
                                    $typeLabel = match ($obligation->obligation_type) {
                                        'insurance' => __('Insurance'),
                                        'tax' => __('Tax / Registration'),
                                        default => __('MOT / Inspection'),
                                    };
                                @endphp
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-medium">{{ $typeLabel }}</div>
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ trim(collect([$obligation->car?->year, $obligation->car?->make, $obligation->car?->model])->filter()->implode(' ')) ?: __('N/A') }}
                                            </div>
                                        </div>
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">{{ $statusLabel }}</span>
                                    </div>
                                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                        <div>
                                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Due Date') }}</dt>
                                            <dd>{{ $obligation->due_date->format('d-m-Y') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Cost') }}</dt>
                                            <dd>{{ \App\Support\CurrencyFormatter::format($obligation->amount, $currencyCode) }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-1">
                            <flux:button variant="ghost" :href="route('obligations.index')" wire:navigate>{{ __('Manage Obligations') }}</flux:button>
                        </div>
                    @endif
                </flux:card>
            </div>
        </div>

        @if ($quickActions->isNotEmpty())
            <div
                x-show="showQuickActionModal && selectedQuickAction"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4"
                x-on:click.self="showQuickActionModal = false"
                x-on:keydown.escape.window="showQuickActionModal = false"
            >
                <div class="w-full max-w-xl rounded-xl border border-zinc-300 bg-white p-5 shadow-2xl ring-1 ring-black/10 dark:border-zinc-600 dark:bg-zinc-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading>{{ __('Run Quick Action') }}</flux:heading>
                            <flux:subheading x-text="selectedQuickAction?.name"></flux:subheading>
                        </div>
                        <flux:button variant="ghost" x-on:click="showQuickActionModal = false">{{ __('Close') }}</flux:button>
                    </div>

                    <div class="mt-4 grid gap-2 text-sm" x-show="!selectedQuickAction?.requires_user_input" x-cloak>
                        <div><strong>{{ __('Target') }}:</strong> <span x-text="selectedQuickAction?.entry_target === 'fuel_log' ? '{{ __('Fuel Log') }}' : (selectedQuickAction?.entry_target === 'mileage_log' ? '{{ __('Mileage Log') }}' : '{{ __('Expense') }}')"></span></div>
                        <div x-show="selectedQuickAction?.entry_target !== 'mileage_log'"><strong>{{ __('Amount') }}:</strong> <span x-text="selectedQuickAction?.amount_display"></span></div>
                        <div x-show="selectedQuickAction?.entry_target === 'fuel_log'">
                            <strong>{{ __('Fuel Volume') }}:</strong>
                            <span x-text="selectedQuickAction?.fuel_volume_display ?? '{{ __('N/A') }}'"></span>
                        </div>
                        <div x-show="selectedQuickAction?.entry_target === 'fuel_log'">
                            <strong>{{ __('Full Tank') }}:</strong>
                            <span x-text="selectedQuickAction?.fuel_full_tank ? '{{ __('Yes') }}' : '{{ __('No') }}'"></span>
                        </div>
                        <div x-show="selectedQuickAction?.entry_target === 'mileage_log'">
                            <strong>{{ __('Miles') }}:</strong>
                            <span x-text="selectedQuickAction?.mileage_distance ? `${selectedQuickAction.mileage_distance} {{ __('miles') }}` : '{{ __('N/A') }}'"></span>
                        </div>
                        <div x-show="selectedQuickAction?.entry_target === 'mileage_log'">
                            <strong>{{ __('Locations') }}:</strong>
                            <span x-text="selectedQuickAction?.mileage_locations || '{{ __('N/A') }}'"></span>
                        </div>
                        <div><strong>{{ __('Car') }}:</strong> <span x-text="selectedQuickAction?.car"></span></div>
                        <div><strong>{{ __('Vendor') }}:</strong> <span x-text="selectedQuickAction?.vendor"></span></div>
                        <div><strong>{{ __('Notes') }}:</strong> <span x-text="selectedQuickAction?.notes"></span></div>
                    </div>

                    <form class="mt-4 flex items-center justify-between gap-3" method="POST" x-bind:action="selectedQuickAction?.run_url">
                        @csrf
                        <div class="flex flex-col gap-3">
                            <div x-show="selectedQuickAction?.entry_target === 'mileage_log'" x-cloak>
                                <flux:input
                                    :label="__('Start Odometer')"
                                    type="number"
                                    name="start_odometer"
                                    min="0"
                                    step="1"
                                    x-model="selectedQuickAction.start_odometer_input"
                                    x-bind:required="selectedQuickAction?.entry_target === 'mileage_log'"
                                />
                            </div>
                            <div x-show="selectedQuickAction?.entry_target === 'mileage_log'" x-cloak>
                                <flux:input
                                    :label="__('Locations')"
                                    type="text"
                                    name="locations"
                                    x-model="selectedQuickAction.mileage_locations"
                                />
                            </div>
                            <div x-show="selectedQuickAction?.entry_target === 'fuel_log'" x-cloak>
                                <flux:input
                                    :label="__('Odometer')"
                                    type="number"
                                    name="odometer"
                                    min="0"
                                    step="1"
                                    x-model="selectedQuickAction.odometer_input"
                                    required
                                />
                            </div>
                            <div x-show="selectedQuickAction?.requires_amount" x-cloak>
                                <flux:input
                                    :label="__('Enter Amount') . ' (' . \App\Support\CurrencyFormatter::symbol($currencyCode) . ')'"
                                    type="number"
                                    name="amount"
                                    min="0.01"
                                    step="0.01"
                                    x-model="selectedQuickAction.amount_input"
                                    x-bind:required="selectedQuickAction?.requires_amount"
                                />
                            </div>
                            <div x-show="selectedQuickAction?.requires_fuel_volume" x-cloak>
                                <flux:input
                                    :label="__('Enter Fuel Volume') . ' (' . (auth()->user()->volume_unit === 'litres' ? 'L' : 'gal') . ')'"
                                    type="number"
                                    name="fuel_volume"
                                    min="0.001"
                                    step="0.001"
                                    x-model="selectedQuickAction.fuel_volume_input"
                                    x-bind:required="selectedQuickAction?.requires_fuel_volume"
                                />
                            </div>
                            <div x-show="selectedQuickAction?.entry_target === 'fuel_log'" x-cloak>
                                <input type="hidden" name="full_tank" x-bind:value="selectedQuickAction?.fuel_full_tank ? '1' : '0'">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" x-model="selectedQuickAction.fuel_full_tank">
                                    <span>{{ __('Full Tank') }}</span>
                                </label>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button type="submit" variant="primary">{{ __('Confirm & Post') }}</flux:button>
                                <flux:button type="button" variant="ghost" x-on:click="showQuickActionModal = false">{{ __('Cancel') }}</flux:button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div
            x-show="showServiceModal && selectedService"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4"
            x-on:click.self="showServiceModal = false"
            x-on:keydown.escape.window="showServiceModal = false"
        >
            <div class="w-full max-w-xl rounded-xl border border-zinc-300 bg-white p-5 shadow-2xl ring-1 ring-black/10 dark:border-zinc-600 dark:bg-zinc-900 dark:ring-white/10">
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
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4"
            x-on:click.self="showRecurringModal = false"
            x-on:keydown.escape.window="showRecurringModal = false"
        >
            <div class="w-full max-w-xl rounded-xl border border-zinc-300 bg-white p-5 shadow-2xl ring-1 ring-black/10 dark:border-zinc-600 dark:bg-zinc-900 dark:ring-white/10">
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
            <flux:card
                class="space-y-4"
                x-data="{
                    showEntryModal: false,
                    isEditingEntry: false,
                    selectedEntry: null,
                    editableLedgerAccounts: {{ Illuminate\Support\Js::from($editableLedgerAccounts) }},
                    editForm: { entry_date: '', account_id: '', amount: '', reference: '', notes: '' },
                    openEntry(entry) {
                        this.selectedEntry = entry;
                        this.isEditingEntry = false;
                        this.editForm = {
                            entry_date: entry.entry_date_raw ?? '',
                            account_id: entry.account_id ? String(entry.account_id) : '',
                            amount: entry.amount_raw ?? '',
                            reference: entry.reference_raw ?? '',
                            notes: entry.notes_raw ?? '',
                        };
                        this.showEntryModal = true;
                    },
                    closeEntryModal() {
                        this.showEntryModal = false;
                        this.isEditingEntry = false;
                    },
                    startEditingEntry() {
                        if (! this.selectedEntry?.editable) return;
                        this.isEditingEntry = true;
                    },
                    accountOptionsForEntry() {
                        return this.editableLedgerAccounts[this.selectedEntry?.entry_type ?? 'expense'] ?? [];
                    }
                }"
            >
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
                    <div class="space-y-3 md:hidden">
                        @foreach ($transactions as $entry)
                            @php
                                $typeLabel = match ($entry->source_type) {
                                    'fuel_log' => 'Fuel',
                                    'maintenance_record' => 'Maintenance',
                                    'vehicle_obligation' => 'Obligation',
                                    'reimbursement' => 'Reimbursement',
                                    'expense' => 'Manual Expense',
                                    'recurring' => 'Recurring',
                                    default => ucfirst((string) $entry->source_type),
                                };
                                $description = $entry->reference ?: ($entry->notes ?: '');
                                $entrySummary = [
                                    'id' => $entry->id,
                                    'date' => $entry->entry_date->format('d-m-Y'),
                                    'type' => __($typeLabel),
                                    'account' => $entry->account?->name ?? __('N/A'),
                                    'description' => $description !== '' ? $description : __('N/A'),
                                    'expense' => $entry->entry_type === 'expense' ? \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) : '-',
                                    'income' => $entry->entry_type === 'income' ? \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) : '-',
                                    'entry_type' => $entry->entry_type,
                                    'entry_date_raw' => $entry->entry_date->format('Y-m-d'),
                                    'account_id' => $entry->account_id,
                                    'amount_raw' => number_format((float) $entry->amount, 2, '.', ''),
                                    'reference_raw' => $entry->reference ?? '',
                                    'notes_raw' => $entry->notes ?? '',
                                    'notes' => $entry->notes ?: __('N/A'),
                                    'reference' => $entry->reference ?: __('N/A'),
                                    'editable' => in_array($entry->source_type, [null, 'manual', 'reimbursement', 'recurring'], true),
                                    'update_url' => route('dashboard.ledger.update', $entry),
                                    'delete_url' => route('dashboard.ledger.delete', $entry),
                                    'edit_help' => match ($entry->source_type) {
                                        'fuel_log' => __('This transaction is managed from the Fuel Log screen.'),
                                        'maintenance_record' => __('This transaction is managed from the Maintenance screen.'),
                                        'expense' => __('This transaction is managed from the Expenses screen.'),
                                        'vehicle_obligation' => __('This transaction is managed from the Obligations screen.'),
                                        default => __('This ledger entry can be edited here.'),
                                    },
                                ];
                            @endphp
                            <button
                                type="button"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 text-left hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:bg-zinc-900"
                                x-on:click='openEntry(@json($entrySummary))'
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ __($typeLabel) }}</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $entry->entry_date->format('d-m-Y') }}</div>
                                    </div>
                                    <div class="text-right text-sm font-semibold {{ $entry->entry_type === 'income' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                        {{ \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) }}
                                    </div>
                                </div>
                                <dl class="mt-3 grid gap-2 text-sm">
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Account') }}</dt>
                                        <dd>{{ $entry->account?->name ?? __('N/A') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Description') }}</dt>
                                        <dd>{{ $description !== '' ? $description : __('N/A') }}</dd>
                                    </div>
                                </dl>
                            </button>
                        @endforeach
                    </div>
                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full min-w-[720px] table-fixed text-left text-sm tabular-nums">
                            <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                <tr>
                                    <th class="w-28 px-3 py-2 font-medium whitespace-nowrap">{{ __('Date') }}</th>
                                    <th class="w-36 px-3 py-2 font-medium whitespace-nowrap">{{ __('Type') }}</th>
                                    <th class="w-40 px-3 py-2 font-medium">{{ __('Account') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Description') }}</th>
                                    <th class="w-28 px-3 py-2 text-right font-medium whitespace-nowrap">{{ __('Expense') }}</th>
                                    <th class="w-28 px-3 py-2 text-right font-medium whitespace-nowrap">{{ __('Income') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transactions as $entry)
                                    @php
                                        $typeLabel = match ($entry->source_type) {
                                            'fuel_log' => 'Fuel',
                                            'maintenance_record' => 'Maintenance',
                                            'vehicle_obligation' => 'Obligation',
                                            'reimbursement' => 'Reimbursement',
                                            'expense' => 'Manual Expense',
                                            'recurring' => 'Recurring',
                                            default => ucfirst((string) $entry->source_type),
                                        };
                                        $description = $entry->reference ?: ($entry->notes ?: '');
                                        $entrySummary = [
                                            'id' => $entry->id,
                                            'date' => $entry->entry_date->format('d-m-Y'),
                                            'type' => __($typeLabel),
                                            'account' => $entry->account?->name ?? __('N/A'),
                                            'description' => $description !== '' ? $description : __('N/A'),
                                            'expense' => $entry->entry_type === 'expense' ? \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) : '-',
                                            'income' => $entry->entry_type === 'income' ? \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) : '-',
                                            'entry_type' => $entry->entry_type,
                                            'entry_date_raw' => $entry->entry_date->format('Y-m-d'),
                                            'account_id' => $entry->account_id,
                                            'amount_raw' => number_format((float) $entry->amount, 2, '.', ''),
                                            'reference_raw' => $entry->reference ?? '',
                                            'notes_raw' => $entry->notes ?? '',
                                            'notes' => $entry->notes ?: __('N/A'),
                                            'reference' => $entry->reference ?: __('N/A'),
                                            'editable' => in_array($entry->source_type, [null, 'manual', 'reimbursement', 'recurring'], true),
                                            'update_url' => route('dashboard.ledger.update', $entry),
                                            'delete_url' => route('dashboard.ledger.delete', $entry),
                                            'edit_help' => match ($entry->source_type) {
                                                'fuel_log' => __('This transaction is managed from the Fuel Log screen.'),
                                                'maintenance_record' => __('This transaction is managed from the Maintenance screen.'),
                                                'expense' => __('This transaction is managed from the Expenses screen.'),
                                                'vehicle_obligation' => __('This transaction is managed from the Obligations screen.'),
                                                default => __('This ledger entry can be edited here.'),
                                            },
                                        ];
                                    @endphp

                                    <tr
                                        class="cursor-pointer border-t border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900/70"
                                        tabindex="0"
                                        x-on:click='openEntry(@json($entrySummary))'
                                        x-on:keydown.enter='openEntry(@json($entrySummary))'
                                    >
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $entry->entry_date->format('d-m-Y') }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ __($typeLabel) }}</td>
                                        <td class="px-3 py-2 truncate">{{ $entry->account?->name ?? __('N/A') }}</td>
                                        <td class="px-3 py-2">
                                            <div class="truncate">{{ $description !== '' ? $description : __('N/A') }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            {{ $entry->entry_type === 'expense' ? \App\Support\CurrencyFormatter::format($entry->amount, $currencyCode) : '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
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
                    x-cloak
                    x-transition.opacity
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4"
                    x-on:click.self="closeEntryModal()"
                    x-on:keydown.escape.window="closeEntryModal()"
                >
                    <div class="w-full max-w-lg rounded-xl border border-zinc-300 bg-white p-5 shadow-2xl ring-1 ring-black/10 dark:border-zinc-600 dark:bg-zinc-900 dark:ring-white/10">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <flux:heading>{{ __('Transaction Summary') }}</flux:heading>
                                <flux:subheading x-text="selectedEntry?.date"></flux:subheading>
                            </div>
                            <flux:button variant="ghost" x-on:click="closeEntryModal()">{{ __('Close') }}</flux:button>
                        </div>

                        <div class="mt-4 grid gap-2 text-sm" x-show="!isEditingEntry">
                            <div><strong>{{ __('Type') }}:</strong> <span x-text="selectedEntry?.type"></span></div>
                            <div><strong>{{ __('Account') }}:</strong> <span x-text="selectedEntry?.account"></span></div>
                            <div><strong>{{ __('Description') }}:</strong> <span x-text="selectedEntry?.description"></span></div>
                            <div><strong>{{ __('Expense') }}:</strong> <span x-text="selectedEntry?.expense"></span></div>
                            <div><strong>{{ __('Income') }}:</strong> <span x-text="selectedEntry?.income"></span></div>
                            <div><strong>{{ __('Reference') }}:</strong> <span x-text="selectedEntry?.reference"></span></div>
                            <div><strong>{{ __('Notes') }}:</strong> <span x-text="selectedEntry?.notes"></span></div>
                        </div>

                        <form method="POST" x-bind:action="selectedEntry?.update_url" class="mt-4 space-y-4" x-show="isEditingEntry">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="transaction_type" value="{{ $selectedTransactionType }}">
                            <input type="hidden" name="period" value="{{ $selectedPeriod }}">
                            @if (request()->has('page'))
                                <input type="hidden" name="page" value="{{ request('page') }}">
                            @endif
                            <div class="grid gap-4 md:grid-cols-2">
                                <flux:input :label="__('Date')" type="date" name="entry_date" x-model="editForm.entry_date" required />
                                <div>
                                    <flux:label for="ledger_account_id">{{ __('Account') }}</flux:label>
                                    <select id="ledger_account_id" name="account_id" x-model="editForm.account_id" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900" required>
                                        <template x-for="account in accountOptionsForEntry()" :key="account.id">
                                            <option :value="String(account.id)" x-text="account.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <flux:input :label="__('Amount')" type="number" min="0.01" step="0.01" name="amount" x-model="editForm.amount" required />
                                <flux:input :label="__('Reference')" type="text" name="reference" x-model="editForm.reference" />
                            </div>
                            <flux:input :label="__('Notes')" type="text" name="notes" x-model="editForm.notes" />

                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <flux:button type="submit" variant="primary">{{ __('Save Ledger Entry') }}</flux:button>
                                    <flux:button type="button" variant="ghost" x-on:click="isEditingEntry = false">{{ __('Cancel') }}</flux:button>
                                </div>
                                <div></div>
                            </div>
                        </form>

                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300" x-show="selectedEntry && !selectedEntry.editable" x-text="selectedEntry?.edit_help"></div>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <flux:button type="button" variant="primary" x-show="selectedEntry?.editable && !isEditingEntry" x-on:click="startEditingEntry()">{{ __('Edit Ledger Entry') }}</flux:button>
                            </div>

                            <form method="POST" x-bind:action="selectedEntry?.delete_url" onsubmit="return confirm('Delete this ledger entry only? Linked obligations will remain, but their financial posting will be removed.');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="transaction_type" value="{{ $selectedTransactionType }}">
                                <input type="hidden" name="period" value="{{ $selectedPeriod }}">
                                @if (request()->has('page'))
                                    <input type="hidden" name="page" value="{{ request('page') }}">
                                @endif
                                <flux:button type="submit" variant="danger">{{ __('Delete Ledger Entry') }}</flux:button>
                            </form>
                        </div>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</x-layouts::app>
