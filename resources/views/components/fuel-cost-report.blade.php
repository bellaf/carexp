<flux:heading size="lg">{{ __('Fuel Costs & Trends') }}</flux:heading>
@if ($fuelCosts['count'] === 0)
    <flux:card><flux:text>{{ __('No fuel receipts found for this period. Try another period or car.') }}</flux:text></flux:card>
@else
    @php
        $number = fn ($value, $decimals = 2) => $value !== null ? number_format($value, $decimals) : __('Unavailable');
        $money = fn ($value) => $value !== null ? \App\Support\CurrencyFormatter::format($value, $currencyCode) : __('Unavailable');
        $stats = [
            __('Total fuel cost') => $money($fuelCosts['spend']),
            __('Average price per litre') => $number($fuelCosts['price']).' '.$fuelCosts['price_unit'],
            __('Litres purchased') => $number($fuelCosts['litres']).' L',
            __('Fill-ups') => $fuelCosts['count'],
            __('Lowest receipt price') => $number($fuelCosts['lowest_price']).' '.$fuelCosts['price_unit'],
            __('Highest receipt price') => $number($fuelCosts['highest_price']).' '.$fuelCosts['price_unit'],
            __('Average fill-up cost') => $money($fuelCosts['average_fill_cost']),
            __('Average fill-up volume') => $number($fuelCosts['average_fill_litres']).' L',
            __('Average efficiency') => $number($fuelCosts['efficiency']).' '.$efficiencyLabel,
            __('Receipts without mileage') => $fuelCosts['missing_mileage'],
        ];
    @endphp
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($stats as $label => $value)
            <flux:card class="space-y-2">
                <flux:text>{{ $label }}</flux:text>
                <flux:heading>{{ $value }}</flux:heading>
            </flux:card>
        @endforeach
    </div>
    <flux:text>{{ __('Prices are weighted by litres purchased. UK imperial gallons are converted to litres. Efficiency uses completed intervals ending in the selected period; combined intervals can span several fill-ups or months.') }}</flux:text>
    @if ($fuelCosts['missing_cost'] > 0)
        <flux:callout variant="warning">{{ __(':count receipts have no recorded cost or unit price. Their volume is included, but they are excluded from price averages.', ['count' => $fuelCosts['missing_cost']]) }}</flux:callout>
    @endif
    <div class="grid gap-4 xl:grid-cols-2">
        @foreach ($fuelCosts['charts'] as $chart)
            <flux:card class="space-y-4">
                <flux:heading>{{ __($chart['title']) }} ({{ $chart['unit'] }})</flux:heading>
                @php
                    $max = max(1, $fuelCosts['months']->max($chart['key']) ?? 0);
                    $width = max(560, $fuelCosts['months']->count() * 72 + 80);
                @endphp
                @if ($fuelCosts['months']->whereNotNull($chart['key'])->isEmpty())
                    <flux:text>{{ __('No completed data available for this chart.') }}</flux:text>
                @else
                    <div class="overflow-x-auto" tabindex="0" aria-label="{{ __($chart['title']) }}">
                        <svg viewBox="0 0 {{ $width }} 250" style="min-width: {{ $width }}px" class="h-64 w-full text-sky-600 dark:text-sky-400" role="img" aria-label="{{ __($chart['title']) }}">
                            <title>{{ __($chart['title']) }}</title>
                            @foreach ([0, 0.5, 1] as $fraction)
                                <line x1="65" y1="{{ 205 - $fraction * 160 }}" x2="{{ $width - 10 }}" y2="{{ 205 - $fraction * 160 }}" stroke="currentColor" opacity="0.15" />
                                <text x="58" y="{{ 209 - $fraction * 160 }}" text-anchor="end" fill="currentColor" font-size="11">{{ number_format($max * $fraction, 1) }}</text>
                            @endforeach
                            @foreach ($fuelCosts['months'] as $row)
                                @php
                                    $value = $row[$chart['key']];
                                    $x = 80 + $loop->index * (($width - 95) / $fuelCosts['months']->count());
                                    $barWidth = min(40, ($width - 95) / $fuelCosts['months']->count() - 14);
                                    $height = $value !== null ? max(0, $value / $max * 160) : 0;
                                @endphp
                                @if ($value !== null)
                                    <rect x="{{ $x }}" y="{{ 205 - $height }}" width="{{ $barWidth }}" height="{{ $height }}" rx="3" fill="currentColor" tabindex="0">
                                        <title>{{ $row['month'] }}: {{ $number($value) }} {{ $chart['unit'] }}</title>
                                    </rect>
                                @endif
                                <text x="{{ $x + $barWidth / 2 }}" y="{{ max(20, 196 - $height) }}" text-anchor="middle" fill="currentColor" font-size="11">{{ $value !== null ? $number($value) : '—' }}</text>
                                <text x="{{ $x + $barWidth / 2 }}" y="230" text-anchor="middle" fill="currentColor" font-size="11">{{ $row['month'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                @endif
            </flux:card>
        @endforeach
    </div>
    <flux:card class="space-y-4">
        <flux:heading>{{ __('Monthly fuel breakdown') }}</flux:heading>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm tabular-nums">
                <thead><tr>
                    @foreach ([__('Month'), __('Cost'), __('Price').' ('.$fuelCosts['price_unit'].')', __('Litres'), __('Fill-ups'), $efficiencyLabel] as $heading)
                        <th class="px-3 py-2 font-medium">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @foreach ($fuelCosts['months'] as $row)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="px-3 py-2">{{ $row['month'] }}</td>
                            <td class="px-3 py-2">{{ $money($row['spend']) }}</td>
                            <td class="px-3 py-2">{{ $number($row['price']) }}</td>
                            <td class="px-3 py-2">{{ $number($row['litres']) }}</td>
                            <td class="px-3 py-2">{{ $row['fills'] }}</td>
                            <td class="px-3 py-2">{{ $number($row['efficiency']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </flux:card>
@endif
