<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\FuelLog;
use App\Models\LedgerEntry;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\VehicleObligation;
use App\Support\CurrencyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ServiceHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $eventTypeOptions = [
            'all' => 'All Events',
            'fuel' => 'Fuel',
            'expense' => 'Expenses',
            'maintenance' => 'Maintenance',
            'obligation' => 'Obligations',
            'reimbursement' => 'Reimbursements',
        ];

        $cars = $user->cars()
            ->orderBy('is_archived')
            ->orderByDesc('is_default')
            ->orderBy('make')
            ->orderBy('model')
            ->get();

        $defaultCarId = $cars->firstWhere('is_default', true)?->id ?? $cars->first()?->id;
        $selectedCarId = $request->filled('car_id')
            ? (int) $request->integer('car_id')
            : $defaultCarId;
        $selectedEventType = $request->string('type')->toString();

        if ($selectedCarId !== null && ! $cars->contains('id', $selectedCarId)) {
            $selectedCarId = $defaultCarId;
        }

        if (! array_key_exists($selectedEventType, $eventTypeOptions)) {
            $selectedEventType = 'all';
        }

        $timeline = $this->timelineEntries($user, $selectedCarId, $selectedEventType);

        return view('history', [
            'cars' => $cars,
            'eventTypeOptions' => $eventTypeOptions,
            'selectedCarId' => $selectedCarId,
            'selectedEventType' => $selectedEventType,
            'timeline' => $timeline,
            'selectedCar' => $selectedCarId !== null ? $cars->firstWhere('id', $selectedCarId) : null,
        ]);
    }

    private function timelineEntries(User $user, ?int $carId, string $eventType): Collection
    {
        return collect()
            ->when(in_array($eventType, ['all', 'fuel'], true), fn (Collection $entries) => $entries->merge($this->fuelEvents($user, $carId)))
            ->when(in_array($eventType, ['all', 'expense'], true), fn (Collection $entries) => $entries->merge($this->expenseEvents($user, $carId)))
            ->when(in_array($eventType, ['all', 'maintenance'], true), fn (Collection $entries) => $entries->merge($this->maintenanceEvents($user, $carId)))
            ->when(in_array($eventType, ['all', 'obligation'], true), fn (Collection $entries) => $entries->merge($this->obligationEvents($user, $carId)))
            ->when(in_array($eventType, ['all', 'reimbursement'], true), fn (Collection $entries) => $entries->merge($this->reimbursementEvents($user, $carId)))
            ->sortByDesc(fn (array $entry): string => $entry['sort_key'])
            ->values();
    }

    private function fuelEvents(User $user, ?int $carId): Collection
    {
        return $user->fuelLogs()
            ->with(['car', 'ledgerEntry'])
            ->when($carId !== null, fn ($query) => $query->where('car_id', $carId))
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FuelLog $fuelLog): array => [
                'id' => 'fuel-'.$fuelLog->id,
                'type' => 'Fuel',
                'date' => $fuelLog->log_date->format('d-m-Y'),
                'sort_key' => $fuelLog->log_date->format('Y-m-d').'|'.str_pad((string) $fuelLog->id, 10, '0', STR_PAD_LEFT),
                'car' => $this->carLabel($fuelLog->car?->year, $fuelLog->car?->make, $fuelLog->car?->model),
                'title' => $fuelLog->full_tank ? 'Fuel Fill-Up' : 'Partial Fuel Fill',
                'subtitle' => $fuelLog->odometer !== null ? 'Odometer '.$fuelLog->odometer : 'No odometer recorded',
                'amount' => CurrencyFormatter::format($fuelLog->ledgerEntry?->amount, $user->preferred_currency),
                'amount_value' => (float) ($fuelLog->ledgerEntry?->amount ?? 0),
                'amount_type' => 'expense',
                'details' => [
                    'Volume' => number_format((float) $fuelLog->volume, 3).' '.($fuelLog->volume_unit === 'liters' ? 'L' : 'gal'),
                    'Price / Unit' => CurrencyFormatter::format((float) $fuelLog->price_per_unit, $user->preferred_currency, 3),
                    'Efficiency' => $fuelLog->calculated_efficiency !== null ? number_format((float) $fuelLog->calculated_efficiency, 3) : 'N/A',
                ],
                'attachments' => [],
                'has_attachments' => false,
                'source_url' => route('fuel.index'),
                'source_label' => 'Open Fuel Logs',
            ]);
    }

    private function expenseEvents(User $user, ?int $carId): Collection
    {
        return $user->expenses()
            ->with(['car', 'category', 'attachments'])
            ->when($carId !== null, fn ($query) => $query->where('car_id', $carId))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => 'expense-'.$expense->id,
                'type' => 'Expense',
                'date' => $expense->expense_date->format('d-m-Y'),
                'sort_key' => $expense->expense_date->format('Y-m-d').'|'.str_pad((string) $expense->id, 10, '0', STR_PAD_LEFT),
                'car' => $this->carLabel($expense->car?->year, $expense->car?->make, $expense->car?->model),
                'title' => $expense->category?->name ?? 'Expense',
                'subtitle' => $expense->vendor ?: 'Manual expense',
                'amount' => CurrencyFormatter::format($expense->amount, $user->preferred_currency),
                'amount_value' => (float) $expense->amount,
                'amount_type' => 'expense',
                'details' => array_filter([
                    'Vendor' => $expense->vendor,
                    'Odometer' => $expense->odometer,
                    'Tags' => is_array($expense->tags) ? implode(', ', $expense->tags) : null,
                    'Notes' => $expense->notes,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
                'attachments' => $this->attachmentPayload($expense->attachments),
                'has_attachments' => $expense->attachments->isNotEmpty(),
                'source_url' => route('expenses.index'),
                'source_label' => 'Open Expenses',
            ]);
    }

    private function maintenanceEvents(User $user, ?int $carId): Collection
    {
        return $user->maintenanceRecords()
            ->with(['car', 'ledgerEntry', 'attachments'])
            ->when($carId !== null, fn ($query) => $query->where('car_id', $carId))
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MaintenanceRecord $record): array => [
                'id' => 'maintenance-'.$record->id,
                'type' => 'Maintenance',
                'date' => $record->service_date->format('d-m-Y'),
                'sort_key' => $record->service_date->format('Y-m-d').'|'.str_pad((string) $record->id, 10, '0', STR_PAD_LEFT),
                'car' => $this->carLabel($record->car?->year, $record->car?->make, $record->car?->model),
                'title' => Str::of($record->service_type)->replace('_', ' ')->headline()->toString(),
                'subtitle' => $record->provider ?: 'Maintenance record',
                'amount' => CurrencyFormatter::format($record->ledgerEntry?->amount, $user->preferred_currency),
                'amount_value' => (float) ($record->ledgerEntry?->amount ?? 0),
                'amount_type' => 'expense',
                'details' => array_filter([
                    'Provider' => $record->provider,
                    'Odometer' => $record->odometer,
                    'Next Due Date' => $record->next_due_date?->format('d-m-Y'),
                    'Next Due Odometer' => $record->next_due_odometer,
                    'Notes' => $record->notes,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
                'attachments' => $this->attachmentPayload($record->attachments),
                'has_attachments' => $record->attachments->isNotEmpty(),
                'source_url' => route('maintenance.index'),
                'source_label' => 'Open Maintenance',
            ]);
    }

    private function obligationEvents(User $user, ?int $carId): Collection
    {
        return $user->vehicleObligations()
            ->with(['car', 'attachments'])
            ->when($carId !== null, fn ($query) => $query->where('car_id', $carId))
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (VehicleObligation $obligation): array => [
                'id' => 'obligation-'.$obligation->id,
                'type' => 'Obligation',
                'date' => $obligation->due_date->format('d-m-Y'),
                'sort_key' => $obligation->due_date->format('Y-m-d').'|'.str_pad((string) $obligation->id, 10, '0', STR_PAD_LEFT),
                'car' => $this->carLabel($obligation->car?->year, $obligation->car?->make, $obligation->car?->model),
                'title' => $this->obligationLabel($obligation->obligation_type),
                'subtitle' => $obligation->provider ?: 'Vehicle obligation',
                'amount' => CurrencyFormatter::format($obligation->amount, $user->preferred_currency),
                'amount_value' => (float) ($obligation->amount ?? 0),
                'amount_type' => 'expense',
                'details' => array_filter([
                    'Provider' => $obligation->provider,
                    'Reference' => $obligation->reference,
                    'Start Date' => $obligation->start_date?->format('d-m-Y'),
                    'Status' => $obligation->completed_at !== null ? 'Completed' : ($obligation->is_active ? 'Active' : 'Inactive'),
                    'Notes' => $obligation->notes,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
                'attachments' => $this->attachmentPayload($obligation->attachments),
                'has_attachments' => $obligation->attachments->isNotEmpty(),
                'source_url' => route('obligations.index'),
                'source_label' => 'Open Obligations',
            ]);
    }

    private function reimbursementEvents(User $user, ?int $carId): Collection
    {
        return $user->ledgerEntries()
            ->with(['car', 'account'])
            ->where('entry_type', 'income')
            ->whereHas('account', fn ($query) => $query->where('group', 'income'))
            ->when($carId !== null, fn ($query) => $query->where('car_id', $carId))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (LedgerEntry $ledgerEntry): array => [
                'id' => 'reimbursement-'.$ledgerEntry->id,
                'type' => 'Reimbursement',
                'date' => $ledgerEntry->entry_date->format('d-m-Y'),
                'sort_key' => $ledgerEntry->entry_date->format('Y-m-d').'|'.str_pad((string) $ledgerEntry->id, 10, '0', STR_PAD_LEFT),
                'car' => $this->carLabel($ledgerEntry->car?->year, $ledgerEntry->car?->make, $ledgerEntry->car?->model),
                'title' => $ledgerEntry->account?->name ?? 'Reimbursement',
                'subtitle' => $ledgerEntry->reference ?: ($ledgerEntry->source_type === 'recurring' ? 'Recurring reimbursement' : 'Incoming reimbursement'),
                'amount' => CurrencyFormatter::format($ledgerEntry->amount, $user->preferred_currency),
                'amount_value' => (float) $ledgerEntry->amount,
                'amount_type' => 'income',
                'details' => array_filter([
                    'Source Type' => str($ledgerEntry->source_type ?? 'reimbursement')->replace('_', ' ')->title()->toString(),
                    'Reference' => $ledgerEntry->reference,
                    'Notes' => $ledgerEntry->notes,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
                'attachments' => [],
                'has_attachments' => false,
                'source_url' => route('reimbursements.index'),
                'source_label' => 'Open Reimbursements',
            ]);
    }

    private function obligationLabel(string $type): string
    {
        return match ($type) {
            'insurance' => 'Insurance',
            'tax' => 'Tax / Registration',
            default => 'MOT / Inspection',
        };
    }

    private function carLabel(?int $year, ?string $make, ?string $model): string
    {
        return trim(collect([$year, $make, $model])->filter()->implode(' '));
    }

    private function attachmentPayload(Collection $attachments): array
    {
        return $attachments
            ->map(fn ($attachment): array => [
                'name' => $attachment->original_name,
                'url' => route('attachments.show', $attachment),
            ])
            ->values()
            ->all();
    }
}
