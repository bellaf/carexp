@if ($fuelCosts['months']->isNotEmpty())
    <div class="space-y-4">
        @foreach ($fuelCosts['charts'] as $chart)
            <flux:card class="space-y-4">
                <flux:heading>{{ __($chart['title']) }} ({{ $chart['unit'] }})</flux:heading>
                @php
                    $max = max(1, $fuelCosts['months']->max($chart['key']) ?? 0) * 1.1;
                    $width = max(700, $fuelCosts['months']->count() * 80 + 100);
                    $points = [];
                    $segments = [];
                    $markers = [];
                    foreach ($fuelCosts['months'] as $index => $row) {
                        $value = $row[$chart['key']];
                        $x = $fuelCosts['months']->count() === 1 ? ($width + 55) / 2 : 75 + $index * (($width - 100) / ($fuelCosts['months']->count() - 1));
                        $y = $value !== null ? 250 - ($value / $max * 210) : null;
                        $markers[] = ['x' => $x, 'y' => $y, 'value' => $value, 'month' => $row['month']];
                        if ($value === null) {
                            if ($points !== []) {
                                $segments[] = implode(' ', $points);
                                $points = [];
                            }
                        } else {
                            $points[] = $x.','.$y;
                        }
                    }
                    if ($points !== []) {
                        $segments[] = implode(' ', $points);
                    }
                @endphp
                @if ($fuelCosts['months']->whereNotNull($chart['key'])->isEmpty())
                    <flux:text>{{ __('No completed data available for this chart.') }}</flux:text>
                @else
                    <div class="overflow-x-auto" tabindex="0" aria-label="{{ __($chart['title']) }}">
                        <svg viewBox="0 0 {{ $width }} 310" style="min-width: {{ $width }}px" class="h-80 w-full text-sky-600 dark:text-sky-400" role="img" aria-label="{{ __($chart['title']) }} line chart">
                            <title>{{ __($chart['title']) }} — {{ $chart['unit'] }}</title>
                            @foreach ([0, 0.25, 0.5, 0.75, 1] as $fraction)
                                <line x1="75" y1="{{ 250 - $fraction * 210 }}" x2="{{ $width - 25 }}" y2="{{ 250 - $fraction * 210 }}" stroke="currentColor" opacity="0.15" />
                                <text x="65" y="{{ 254 - $fraction * 210 }}" text-anchor="end" fill="currentColor" font-size="12">{{ number_format($max * $fraction, 1) }}</text>
                            @endforeach
                            @foreach ($segments as $segment)
                                <polyline points="{{ $segment }}" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            @endforeach
                            @foreach ($markers as $marker)
                                @if ($marker['value'] !== null)
                                    <circle cx="{{ $marker['x'] }}" cy="{{ $marker['y'] }}" r="5" fill="currentColor" tabindex="0">
                                        <title>{{ $marker['month'] }}: {{ number_format($marker['value'], 2) }} {{ $chart['unit'] }}</title>
                                    </circle>
                                    <text x="{{ $marker['x'] }}" y="{{ $marker['y'] - 12 }}" text-anchor="middle" fill="currentColor" font-size="12">{{ number_format($marker['value'], 2) }}</text>
                                @endif
                                <text x="{{ $marker['x'] }}" y="280" text-anchor="middle" fill="currentColor" font-size="12">{{ $marker['month'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                @endif
            </flux:card>
        @endforeach
    </div>
    <flux:text>{{ __('Monthly prices are weighted by litres purchased; UK imperial gallons are converted to litres. Gaps indicate unavailable prices or efficiency. Efficiency covers completed intervals ending in each month, which may span multiple fill-ups.') }}</flux:text>
@endif
