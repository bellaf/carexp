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
            <div class="grid gap-4 md:grid-cols-4">
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Transactions') }}</flux:text>
                    <flux:heading>{{ $summary['transaction_count'] }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Expenses') }}</flux:text>
                    <flux:heading class="text-rose-600 dark:text-rose-400">{{ $summary['expense_total'] }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Reimbursements') }}</flux:text>
                    <flux:heading class="text-emerald-600 dark:text-emerald-400">{{ $summary['income_total'] }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Net Cost') }}</flux:text>
                    <flux:heading class="{{ $summary['net_cost_value'] < 0 ? 'text-emerald-600 dark:text-emerald-400' : ($summary['net_cost_value'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-zinc-100') }}">{{ $summary['net_cost'] }}</flux:heading>
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
                                        <dd>{{ $row['net_cost'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full text-left text-sm">
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
                                        <td class="px-3 py-2 text-right">{{ $row['net_cost'] }}</td>
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
                                        <dd>{{ $row['net_cost'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full text-left text-sm">
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
                                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                        <td class="px-3 py-2">{{ $row['category'] }}</td>
                                        <td class="px-3 py-2 text-right text-rose-700 dark:text-rose-400">{{ $row['expense_total'] }}</td>
                                        <td class="px-3 py-2 text-right text-emerald-700 dark:text-emerald-400">{{ $row['income_total'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['net_cost'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        @endif

        @if ($selectedReport === 'fuel')
            <div class="grid gap-4 md:grid-cols-4">
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Fill-Ups') }}</flux:text>
                    <flux:heading>{{ $fuelSummary['fill_count'] }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Fuel Spend') }}</flux:text>
                    <flux:heading>{{ $fuelSummary['total_spend'] }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Volume') }}</flux:text>
                    <flux:heading>{{ $fuelSummary['total_volume'] }} {{ $volumeLabel }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Avg Price / Volume') }}</flux:text>
                    <flux:heading>{{ $fuelSummary['average_price'] }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Avg efficiency') }}: {{ $fuelSummary['average_efficiency'] }} {{ $efficiencyLabel }}</flux:text>
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
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Month') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Fill-Ups') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Spend') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Volume') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Avg Efficiency') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($fuelMonthlyRows as $row)
                                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                        <td class="px-3 py-2">{{ $row['month'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['fill_count'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['total_spend'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['total_volume'] }} {{ $volumeLabel }}</td>
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
                        <table class="w-full text-left text-sm">
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
                    <div class="space-y-3 md:hidden">
                        @foreach ($ownershipRows as $row)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="mb-3 font-medium">{{ $row['car'] }}</div>
                                <dl class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                                        <dd>{{ $row['status'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Distance') }}</dt>
                                        <dd>{{ $row['distance'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Purchase Price') }}</dt>
                                        <dd>{{ $row['purchase_price'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Sale Price') }}</dt>
                                        <dd>{{ $row['sale_price'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Net Cost') }}</dt>
                                        <dd>{{ $row['net_cost'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Total Ownership Cost') }}</dt>
                                        <dd>{{ $row['total_ownership_cost'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Net Cost / Distance') }}</dt>
                                        <dd>{{ $row['net_cost_per_distance'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Fuel Cost / Distance') }}</dt>
                                        <dd>{{ $row['fuel_cost_per_distance'] }}</dd>
                                    </div>
                                    <div class="col-span-2">
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Maintenance Cost / Distance') }}</dt>
                                        <dd>{{ $row['maintenance_cost_per_distance'] }}</dd>
                                    </div>
                                    <div class="col-span-2">
                                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Total Ownership Cost / Distance') }}</dt>
                                        <dd>{{ $row['total_ownership_cost_per_distance'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('Car') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Distance') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Purchase Price') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Sale Price') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Expenses') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Reimbursements') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Net Cost') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Total Ownership Cost') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Net Cost / Distance') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Fuel Cost / Distance') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Maintenance Cost / Distance') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Total Ownership Cost / Distance') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ownershipRows as $row)
                                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                        <td class="px-3 py-2">{{ $row['car'] }}</td>
                                        <td class="px-3 py-2">{{ $row['status'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['distance'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['purchase_price'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['sale_price'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['expense_total'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['income_total'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['net_cost'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['total_ownership_cost'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['net_cost_per_distance'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['fuel_cost_per_distance'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['maintenance_cost_per_distance'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $row['total_ownership_cost_per_distance'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        @endif
    </div>
</x-layouts::app>
