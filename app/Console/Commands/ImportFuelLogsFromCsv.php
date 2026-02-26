<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Car;
use App\Models\FuelLog;
use App\Models\LedgerEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportFuelLogsFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-fuel-logs-from-csv
        {--file= : Path to CSV file (defaults to single CSV in project root)}
        {--user-id= : User ID that owns imported records}
        {--car-id= : Car ID to assign imported fuel logs}
        {--volume-unit=liters : Volume unit (liters or gallons)}
        {--date-format=d/m/Y : Date format used in CSV}
        {--full-tank=1 : Mark imported rows as full tank (1 or 0)}
        {--dry-run : Validate CSV and show summary without writing}
        {--allow-duplicates : Import duplicates instead of skipping existing rows}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import fuel logs from a CSV file and create linked ledger entries';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = (int) $this->option('user-id');

        if ($userId <= 0) {
            $this->error('Missing required option: --user-id');

            return self::FAILURE;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            $this->error("User {$userId} not found.");

            return self::FAILURE;
        }

        $car = $this->resolveCar($user);

        if ($car === null) {
            return self::FAILURE;
        }

        $csvPath = $this->resolveCsvPath();

        if ($csvPath === null) {
            return self::FAILURE;
        }

        $volumeUnit = (string) $this->option('volume-unit');

        if (! in_array($volumeUnit, ['liters', 'gallons'], true)) {
            $this->error('Invalid --volume-unit value. Use "liters" or "gallons".');

            return self::FAILURE;
        }

        $fullTank = (string) $this->option('full-tank') === '1';
        $dateFormat = (string) $this->option('date-format');
        $dryRun = (bool) $this->option('dry-run');
        $allowDuplicates = (bool) $this->option('allow-duplicates');

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            $this->error("Unable to open CSV file: {$csvPath}");

            return self::FAILURE;
        }

        $headers = fgetcsv($handle);

        if ($headers === false || $headers === []) {
            fclose($handle);
            $this->error('CSV appears empty or has no header row.');

            return self::FAILURE;
        }

        $headerMap = $this->buildHeaderMap($headers);

        $dateColumn = $this->findHeaderIndex($headerMap, ['date', 'logdate']);
        $odometerColumn = $this->findHeaderIndex($headerMap, ['odoread', 'odometer', 'odo']);
        $volumeColumn = $this->findHeaderIndex($headerMap, ['litres', 'liters', 'volume']);
        $costColumn = $this->findHeaderIndex($headerMap, ['cost', 'totalcost', 'amount']);
        $priceColumn = $this->findHeaderIndex($headerMap, ['perl', 'priceperl', 'priceperunit', 'unitprice']);
        $efficiencyColumn = $this->findHeaderIndex($headerMap, ['mpg', 'kmpl', 'efficiency']);

        if ($dateColumn === null || $odometerColumn === null || $volumeColumn === null || $costColumn === null) {
            fclose($handle);
            $this->error('CSV must include date, odometer, volume, and cost columns.');

            return self::FAILURE;
        }

        $fuelAccountId = (int) Account::query()->firstOrCreate(
            ['key' => 'fuel_expense'],
            [
                'user_id' => null,
                'name' => 'Fuel',
                'group' => 'expense',
                'is_system' => true,
                'is_active' => true,
            ],
        )->id;

        $lineNumber = 1;
        $rowsRead = 0;
        $imported = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $rowsRead++;

            $dateText = trim((string) ($row[$dateColumn] ?? ''));
            $odometer = $this->parseInteger((string) ($row[$odometerColumn] ?? ''));
            $volume = $this->parseDecimal((string) ($row[$volumeColumn] ?? ''));
            $amount = $this->parseDecimal((string) ($row[$costColumn] ?? ''));
            $price = $priceColumn !== null ? $this->parseDecimal((string) ($row[$priceColumn] ?? '')) : null;
            $efficiency = $efficiencyColumn !== null ? $this->parseDecimal((string) ($row[$efficiencyColumn] ?? '')) : null;

            if ($dateText === '' || $odometer === null || $volume === null || $amount === null || $volume <= 0 || $amount <= 0) {
                $skipped++;
                $this->warn("Line {$lineNumber}: skipped (missing/invalid date, odometer, volume, or cost).");

                continue;
            }

            try {
                $logDate = CarbonImmutable::createFromFormat($dateFormat, $dateText)->startOfDay();
            } catch (\Throwable) {
                $skipped++;
                $this->warn("Line {$lineNumber}: skipped (unable to parse date '{$dateText}' with format {$dateFormat}).");

                continue;
            }

            $existing = FuelLog::query()
                ->where('user_id', $user->id)
                ->where('car_id', $car->id)
                ->whereDate('log_date', $logDate->format('Y-m-d'))
                ->where('odometer', $odometer)
                ->where('volume', round($volume, 3))
                ->first();

            if ($existing !== null && ! $allowDuplicates) {
                $duplicates++;

                continue;
            }

            if ($dryRun) {
                $imported++;

                continue;
            }

            try {
                DB::transaction(function () use ($user, $car, $fuelAccountId, $logDate, $odometer, $volume, $volumeUnit, $price, $fullTank, $efficiency, $amount): void {
                    $fuelLog = FuelLog::query()->create([
                        'user_id' => $user->id,
                        'car_id' => $car->id,
                        'log_date' => $logDate->format('Y-m-d'),
                        'odometer' => $odometer,
                        'volume' => round($volume, 3),
                        'volume_unit' => $volumeUnit,
                        'price_per_unit' => $price !== null && $price > 0 ? round($price, 3) : round($amount / $volume, 3),
                        'full_tank' => $fullTank,
                        'calculated_efficiency' => $efficiency !== null && $efficiency > 0 ? round($efficiency, 3) : null,
                    ]);

                    $entry = LedgerEntry::query()->create([
                        'user_id' => $user->id,
                        'car_id' => $car->id,
                        'account_id' => $fuelAccountId,
                        'entry_date' => $logDate->format('Y-m-d'),
                        'entry_type' => 'expense',
                        'amount' => round($amount, 2),
                        'source_type' => 'fuel_log',
                        'source_id' => $fuelLog->id,
                        'reference' => null,
                        'notes' => null,
                    ]);

                    $fuelLog->update(['ledger_entry_id' => $entry->id]);
                });
            } catch (\Throwable $exception) {
                $errors++;
                $this->warn("Line {$lineNumber}: import failed ({$exception->getMessage()}).");

                continue;
            }

            $imported++;
        }

        fclose($handle);

        if (! $dryRun) {
            $this->syncCarCurrentOdometer($car);
        }

        $this->newLine();
        $this->info('Fuel CSV import summary');
        $this->line("File: {$csvPath}");
        $this->line("User ID: {$user->id}");
        $this->line("Car ID: {$car->id}");
        $this->line("Rows read: {$rowsRead}");
        $this->line($dryRun ? "Rows valid for import: {$imported}" : "Rows imported: {$imported}");
        $this->line("Rows skipped: {$skipped}");
        $this->line("Rows treated as duplicates: {$duplicates}");
        $this->line("Rows failed: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function resolveCar(User $user): ?Car
    {
        $carId = (int) $this->option('car-id');

        if ($carId > 0) {
            $car = $user->cars()->find($carId);

            if ($car === null) {
                $this->error("Car {$carId} not found for user {$user->id}.");

                return null;
            }

            return $car;
        }

        $car = $user->cars()
            ->where('is_archived', false)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($car === null) {
            $this->error('No active car found for this user. Provide --car-id.');

            return null;
        }

        return $car;
    }

    protected function resolveCsvPath(): ?string
    {
        $fileOption = (string) ($this->option('file') ?? '');

        if ($fileOption !== '') {
            $candidatePath = str_starts_with($fileOption, '/')
                ? $fileOption
                : base_path($fileOption);

            if (! is_file($candidatePath)) {
                $this->error("CSV file not found: {$candidatePath}");

                return null;
            }

            return $candidatePath;
        }

        $csvFiles = glob(base_path('*.csv')) ?: [];

        if (count($csvFiles) !== 1) {
            $this->error('Could not auto-select CSV file. Provide --file=<path>.');

            return null;
        }

        return (string) $csvFiles[0];
    }

    /**
     * @param  list<string>  $headers
     * @return array<int, string>
     */
    protected function buildHeaderMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            $map[(int) $index] = $normalized;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $headerMap
     * @param  list<string>  $targets
     */
    protected function findHeaderIndex(array $headerMap, array $targets): ?int
    {
        foreach ($headerMap as $index => $normalizedHeader) {
            if (in_array($normalizedHeader, $targets, true)) {
                return $index;
            }
        }

        return null;
    }

    protected function normalizeHeader(string $header): string
    {
        $trimmed = strtolower(trim($header));

        return (string) preg_replace('/[^a-z0-9]+/', '', $trimmed);
    }

    /**
     * @param  list<string|null>  $row
     */
    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function parseDecimal(string $value): ?float
    {
        $sanitized = (string) preg_replace('/[^0-9.\-]+/', '', trim($value));

        if ($sanitized === '' || $sanitized === '-' || $sanitized === '.') {
            return null;
        }

        return is_numeric($sanitized) ? (float) $sanitized : null;
    }

    protected function parseInteger(string $value): ?int
    {
        $sanitized = (string) preg_replace('/[^0-9\-]+/', '', trim($value));

        if ($sanitized === '' || $sanitized === '-') {
            return null;
        }

        return is_numeric($sanitized) ? (int) $sanitized : null;
    }

    protected function syncCarCurrentOdometer(Car $car): void
    {
        $latestLog = FuelLog::query()
            ->where('car_id', $car->id)
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->first();

        $car->update([
            'current_odometer' => $latestLog?->odometer,
        ]);
    }
}
