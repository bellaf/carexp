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

                <div class="w-full sm:w-56">
                    <flux:label for="period">{{ __('Period') }}</flux:label>
                    <select id="period" name="period" onchange="this.form.submit()" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($periodOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedPeriod === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($selectedPeriod === 'full_year')
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
    </div>
</x-layouts::app>
