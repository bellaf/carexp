<x-layouts::app :title="__('Reports')">
    <div class="w-full space-y-6">
        <div>
            <flux:heading size="xl">{{ __('Reports') }}</flux:heading>
            <flux:subheading>{{ __('Simple financial and fuel reports from your logged data.') }}</flux:subheading>
        </div>

        <flux:card class="space-y-4">
            <form method="GET" action="{{ route('reports.index') }}" class="space-y-3 md:space-y-0 md:flex md:flex-wrap md:items-end md:gap-3">
                <div class="w-full sm:w-56">
                    <flux:label for="report">{{ __('Report') }}</flux:label>
                    <select id="report" name="report" onchange="this.form.submit()" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($reportOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedReport === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($selectedReport !== 'ownership')
                    <div class="w-full sm:w-56">
                        <flux:label for="period">{{ __('Period') }}</flux:label>
                        <select id="period" name="period" onchange="this.form.submit()" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($periodOptions as $value => $label)
                                <option value="{{ $value }}" @selected($selectedPeriod === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($selectedReport !== 'ownership' && $selectedPeriod === 'full_year')
                    <div class="w-full sm:w-40">
                        <flux:label for="year">{{ __('Year') }}</flux:label>
                        <select id="year" name="year" onchange="this.form.submit()" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            @for ($year = now()->year; $year >= 2020; $year--)
                                <option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }}</option>
                            @endfor
                        </select>
                    </div>
                @endif

                <div class="w-full sm:w-64">
                    <flux:label for="car_id">{{ __('Car') }}</flux:label>
                    <select id="car_id" name="car_id" onchange="this.form.submit()" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">{{ __('All Cars') }}</option>
                        @foreach ($cars as $car)
                            <option value="{{ $car->id }}" @selected($selectedCarId === $car->id)>{{ trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </flux:card>

        @if ($selectedReport === 'summary')
            <div class="grid gap-4 md:grid-cols-3">
                <flux:card class="space-y-3">
                    <flux:text>{{ __('Expenses') }}</flux:text>
                    <flux:heading class="text-rose-600 dark:text-rose-400">{{ $summary['expense_total'] }}</flux:heading>
                    @if ($summarySparklines['expenses'] !== null)
                        <svg viewBox="0 0 100 36" class="h-10 w-full overflow-visible" aria-label="{{ __('Expense trend sparkline') }}">
                            <polyline points="{{ $summarySparklines['expenses']['points'] }}" fill="none" stroke="currentColor" stroke-width="3" class="text-rose-500 dark:text-rose-400" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </flux:card>
                <flux:card class="space-y-3">
                    <flux:text>{{ __('Reimbursements') }}</flux:text>
                    <flux:heading class="text-emerald-600 dark:text-emerald-400">{{ $summary['income_total'] }}</flux:heading>
                    @if ($summarySparklines['reimbursements'] !== null)
                        <svg viewBox="0 0 100 36" class="h-10 w-full overflow-visible" aria-label="{{ __('Reimbursement trend sparkline') }}">
                            <polyline points="{{ $summarySparklines['reimbursements']['points'] }}" fill="none" stroke="currentColor" stroke-width="3" class="text-emerald-500 dark:text-emerald-400" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </flux:card>
                <flux:card class="space-y-3">
                    <flux:text>{{ __('Net Cost') }}</flux:text>
                    <flux:heading class="{{ $summary['net_cost_value'] < 0 ? 'text-emerald-600 dark:text-emerald-400' : ($summary['net_cost_value'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100') }}">{{ $summary['net_cost'] }}</flux:heading>
                    @if ($summarySparklines['net_cost'] !== null)
                        <svg viewBox="0 0 100 36" class="h-10 w-full overflow-visible" aria-label="{{ __('Net cost trend sparkline') }}">
                            <polyline points="{{ $summarySparklines['net_cost']['points'] }}" fill="none" stroke="currentColor" stroke-width="3" class="{{ $summary['net_cost_value'] < 0 ? 'text-emerald-500 dark:text-emerald-400' : ($summary['net_cost_value'] > 0 ? 'text-rose-500 dark:text-rose-400' : 'text-zinc-500 dark:text-zinc-400') }}" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </flux:card>
            </div>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Monthly Trend') }}</flux:heading>

                @if ($monthlyRows->isEmpty())
                    <flux:text>{{ __('No data found for this period.') }}</flux:text>
                @else
                    <div class="space-y-3 md:hidden">
                        @foreach ($monthlyRows as $row)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="mb-3 font-medium">{{ $row['month'] }}</div>
                                <dl class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Expenses') }}</dt>
                                        <dd class="text-rose-700 dark:text-rose-400">{{ $row['expense_total'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Reimbursements') }}</dt>
                                        <dd class="text-emerald-700 dark:text-emerald-400">{{ $row['income_total'] }}</dd>
                                    </div>
                                    <div class="col-span-2">
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Net Cost') }}</dt>
                                        <dd class="{{ $row['net_cost_value'] < 0 ? 'text-emerald-700 dark:text-emerald-400' : ($row['net_cost_value'] > 0 ? 'text-rose-700 dark:text-rose-400' : '') }}">{{ $row['net_cost'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full text-left text-sm tabular-nums">
                            <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Month') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Expenses') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Reimbursements') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Net Cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($monthlyRows as $row)
                                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                        <td class="px-3 py-2">{{ $row['month'] }}</td>
                                        <td class="px-3 py-2 text-right text-rose-700 dark:text-rose-400">{{ $row['expense_total'] }}</td>
                                        <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">{{ $row['income_total'] }}</td>
                                        <td class="px-3 py-2 text-right {{ $row['net_cost_value'] < 0 ? 'text-emerald-700 dark:text-emerald-400' : ($row['net_cost_value'] > 0 ? 'text-rose-700 dark:text-rose-400' : '') }}">{{ $row['net_cost'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        @endif

        @if ($selectedReport === 'category')
            <flux:card class="space-y-3">
                <flux:heading>{{ __('Category Breakdown') }}</flux:heading>

                @if ($categoryRows->isEmpty())
                    <flux:text>{{ __('No data found for this period.') }}</flux:text>
                @else
                    <div class="space-y-3 md:hidden">
                                @foreach ($categoryRows as $row)
                                    @php
                                        $isNetCostNegative = (float) $row['net_cost_value'] < 0;
                                    @endphp
                                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="mb-3 font-medium">{{ $row['category'] }}</div>
                                <dl class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Expenses') }}</dt>
                                        <dd class="text-rose-700 dark:text-rose-400">{{ $row['expense_total'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Reimbursements') }}</dt>
                                        <dd class="text-emerald-700 dark:text-emerald-400">{{ $row['income_total'] }}</dd>
                                    </div>
                                    <div class="col-span-2">
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Net Cost') }}</dt>
                                        <dd class="{{ $isNetCostNegative ? 'text-emerald-700 dark:text-emerald-400' : '' }}">{{ $row['net_cost'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full text-left text-sm tabular-nums">
                            <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Category') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Expenses') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Reimbursements') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Net Cost') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categoryRows as $row)
                                    @php
                                        $isNetCostNegative = (float) $row['net_cost_value'] < 0;
                                    @endphp
                                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                        <td class="px-3 py-2">{{ $row['category'] }}</td>
                                        <td class="px-3 py-2 text-right text-rose-700 dark:text-rose-400">{{ $row['expense_total'] }}</td>
                                        <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">{{ $row['income_total'] }}</td>
                                        <td class="px-3 py-2 text-right {{ $isNetCostNegative ? 'text-emerald-700 dark:text-emerald-400' : '' }}">{{ $row['net_cost'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        @endif

        @if ($selectedReport === 'fuel')
            <div class="grid gap-4 md:grid-cols-2">
                <flux:card class="space-y-3">
                    <flux:text>{{ __('Fuel Spend') }}</flux:text>
                    <flux:heading>{{ $fuelSummary['total_spend'] }}</flux:heading>
                    @if ($fuelSparklines['spend'] !== null)
                        <svg viewBox="0 0 100 36" class="h-10 w-full overflow-visible" aria-label="{{ __('Fuel spend trend sparkline') }}">
                            <polyline points="{{ $fuelSparklines['spend']['points'] }}" fill="none" stroke="currentColor" stroke-width="3" class="text-rose-500 dark:text-rose-400" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </flux:card>
                <flux:card class="space-y-3">
                    <flux:text>{{ __('Avg Price / Volume') }}</flux:text>
                    <flux:heading>{{ $fuelSummary['average_price'] }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Avg efficiency') }}: {{ $fuelSummary['average_efficiency'] }} {{ $efficiencyLabel }}</flux:text>
                    @if ($fuelSparklines['efficiency'] !== null)
                        <svg viewBox="0 0 100 36" class="h-10 w-full overflow-visible" aria-label="{{ __('Fuel efficiency trend sparkline') }}">
                            <polyline points="{{ $fuelSparklines['efficiency']['points'] }}" fill="none" stroke="currentColor" stroke-width="3" class="text-emerald-500 dark:text-emerald-400" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </flux:card>
            </div>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Fuel Trend') }}</flux:heading>

                @if ($fuelMonthlyRows->isEmpty())
                    <flux:text>{{ __('No fuel data found for this period.') }}</flux:text>
                @else
                    <div class="space-y-3 md:hidden">
                        @foreach ($fuelMonthlyRows as $row)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="mb-3 font-medium">{{ $row['month'] }}</div>
                                <dl class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Fill-Ups') }}</dt>
                                        <dd>{{ $row['fill_count'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Spend') }}</dt>
                                        <dd>{{ $row['total_spend'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Volume') }}</dt>
                                        <dd>{{ $row['total_volume'] }} {{ $volumeLabel }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Avg Efficiency') }}</dt>
                                        <dd>{{ $row['average_efficiency'] }} {{ $efficiencyLabel }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full text-left text-sm tabular-nums">
                            <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Month') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Fill-Ups') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Spend') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Avg Efficiency') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($fuelMonthlyRows as $row)
                                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                        <td class="px-3 py-2">{{ $row['month'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['fill_count'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['total_spend'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['average_efficiency'] }} {{ $efficiencyLabel }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        @endif

        @if ($selectedReport === 'obligations')
            <div class="grid gap-4 md:grid-cols-4">
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Active') }}</flux:text>
                    <flux:heading>{{ $obligationSummary['active_count'] }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Due Soon') }}</flux:text>
                    <flux:heading class="text-amber-600 dark:text-amber-400">{{ $obligationSummary['due_soon_count'] }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Overdue') }}</flux:text>
                    <flux:heading class="text-rose-600 dark:text-rose-400">{{ $obligationSummary['overdue_count'] }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Renewal Cost') }}</flux:text>
                    <flux:heading>{{ $obligationSummary['total_cost'] }}</flux:heading>
                </flux:card>
            </div>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Obligation Schedule') }}</flux:heading>

                @if ($obligationRows->isEmpty())
                    <flux:text>{{ __('No obligation data found for this period.') }}</flux:text>
                @else
                    <div class="space-y-3 md:hidden">
                        @foreach ($obligationRows as $row)
                            @php
                                $statusClasses = match ($row['status_key']) {
                                    'overdue' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
                                    'due_soon' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
                                    'completed' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300',
                                    'inactive' => 'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                                    default => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
                                };
                            @endphp
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="mb-3 flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ $row['type'] }}</div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $row['car'] }}</div>
                                    </div>
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">{{ $row['status'] }}</span>
                                </div>
                                <dl class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Due Date') }}</dt>
                                        <dd>{{ $row['due_date'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Cost') }}</dt>
                                        <dd>{{ $row['cost'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Provider') }}</dt>
                                        <dd>{{ $row['provider'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Reference') }}</dt>
                                        <dd>{{ $row['reference'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full text-left text-sm tabular-nums">
                            <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Type') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Car') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Due Date') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Provider') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Reference') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Cost') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($obligationRows as $row)
                                    @php
                                        $statusClasses = match ($row['status_key']) {
                                            'overdue' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
                                            'due_soon' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
                                            'completed' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300',
                                            'inactive' => 'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                                            default => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
                                        };
                                    @endphp
                                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                        <td class="px-3 py-2">{{ $row['type'] }}</td>
                                        <td class="px-3 py-2">{{ $row['car'] }}</td>
                                        <td class="px-3 py-2">{{ $row['due_date'] }}</td>
                                        <td class="px-3 py-2">{{ $row['provider'] }}</td>
                                        <td class="px-3 py-2">{{ $row['reference'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['cost'] }}</td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">{{ $row['status'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        @endif

        @if ($selectedReport === 'ownership')
            <flux:card class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading>{{ __('Ownership Metrics') }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('All-time running costs from purchase to current odometer.') }}</flux:text>
                </div>

                @if ($ownershipRows->isEmpty())
                    <flux:text>{{ __('No ownership data is available yet.') }}</flux:text>
                @else
                    <div class="space-y-4">
                        @foreach ($ownershipRows as $row)
                            @php
                                $status = strtolower(trim((string) ($row['status'] ?? '')));
                                $statusClasses = match ($status) {
                                    'active' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
                                    'sold' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300',
                                    'inactive' => 'border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                                    default => 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-300',
                                };

                                $netCostNegative = str_contains((string) ($row['net_cost'] ?? ''), '-');
                                $netCostPositive = str_contains((string) ($row['net_cost'] ?? ''), '+');
                                $netCostClasses = $netCostNegative
                                    ? 'text-emerald-700 dark:text-emerald-300'
                                    : ($netCostPositive ? 'text-rose-700 dark:text-rose-300' : 'text-zinc-900 dark:text-zinc-100');
                            @endphp
                            <flux:card class="space-y-4 border-l-4 {{ $statusClasses }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ $row['car'] }}</div>
                                        <div>
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium border-current/20 bg-current/10 {{ str_replace('text-zinc-700 dark:text-zinc-300', '', (string) $statusClasses) }}">
                                                {{ $row['status'] }}
                                            </span>
                                        </div>
                                    </div>
                                    <flux:heading class="{{ $netCostClasses }}">{{ __('Net Cost') }}: {{ $row['net_cost'] }}</flux:heading>
                                </div>

                                <div class="grid gap-3 md:grid-cols-3">
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Distance') }}</div>
                                        <div class="mt-0.5 text-sky-700 dark:text-sky-300">{{ $row['distance'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Purchase Price') }}</div>
                                        <div class="mt-0.5 text-rose-700 dark:text-rose-300">{{ $row['purchase_price'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Sale Price') }}</div>
                                        <div class="mt-0.5 text-amber-700 dark:text-amber-300">{{ $row['sale_price'] }}</div>
                                    </div>
                                </div>

                                <div class="grid gap-3 md:grid-cols-3">
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Expenses') }}</div>
                                        <div class="mt-0.5 text-rose-700 dark:text-rose-300">{{ $row['expense_total'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Reimbursements') }}</div>
                                        <div class="mt-0.5 text-emerald-700 dark:text-emerald-300">{{ $row['income_total'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total Ownership Cost') }}</div>
                                        <div class="mt-0.5 text-zinc-900 dark:text-zinc-100">{{ $row['total_ownership_cost'] }}</div>
                                    </div>
                                </div>

                                <div class="grid gap-3 md:grid-cols-3">
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Net Cost / Distance') }}</div>
                                        <div class="mt-0.5 {{ $netCostClasses }}">{{ $row['net_cost_per_distance'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Fuel Cost / Distance') }}</div>
                                        <div class="mt-0.5 text-sky-700 dark:text-sky-300">{{ $row['fuel_cost_per_distance'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Maintenance Cost / Distance') }}</div>
                                        <div class="mt-0.5 text-amber-700 dark:text-amber-300">{{ $row['maintenance_cost_per_distance'] }}</div>
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total Ownership Cost / Distance') }}</div>
                                    <div class="mt-0.5 text-purple-700 dark:text-purple-300">{{ $row['total_ownership_cost_per_distance'] }}</div>
                                </div>
                            </flux:card>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        @endif
    </div>
</x-layouts::app>
