<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

class BackupStatusReporter
{
    /**
     * @return array{
     *     disk:string,
     *     root:string,
     *     maximum_age_days:int,
     *     total_backups:int,
     *     latest_backup:array{name:string,path:string,size:string,size_bytes:int,last_modified:string,last_modified_timestamp:int}|null,
     *     recent_backups:list<array{name:string,path:string,size:string,size_bytes:int,last_modified:string,last_modified_timestamp:int}>,
     *     is_healthy:bool
     * }
     */
    public function summary(): array
    {
        $disk = (string) (config('backup.backup.destination.disks.0') ?? 'backups');
        $root = trim((string) config('backup.backup.name', config('app.name', 'carexp')), '/');
        $maximumAgeDays = (int) (config('backup.monitor_backups.0.health_checks')[\Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class] ?? 1);

        $recentBackups = collect(Storage::disk($disk)->allFiles($root))
            ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
            ->map(function (string $path) use ($disk): array {
                $lastModifiedTimestamp = Storage::disk($disk)->lastModified($path);
                $sizeBytes = Storage::disk($disk)->size($path);

                return [
                    'name' => basename($path),
                    'path' => $path,
                    'size' => $this->formatBytes($sizeBytes),
                    'size_bytes' => $sizeBytes,
                    'last_modified' => CarbonImmutable::createFromTimestamp($lastModifiedTimestamp)->format('d-m-Y H:i'),
                    'last_modified_timestamp' => $lastModifiedTimestamp,
                ];
            })
            ->sortByDesc('last_modified_timestamp')
            ->values();

        /** @var array{name:string,path:string,size:string,size_bytes:int,last_modified:string,last_modified_timestamp:int}|null $latestBackup */
        $latestBackup = $recentBackups->first();
        $cutoffTimestamp = now()->subDays($maximumAgeDays)->timestamp;

        return [
            'disk' => $disk,
            'root' => $root,
            'maximum_age_days' => $maximumAgeDays,
            'total_backups' => $recentBackups->count(),
            'latest_backup' => $latestBackup,
            'recent_backups' => $recentBackups->take(10)->all(),
            'is_healthy' => $latestBackup !== null && $latestBackup['last_modified_timestamp'] >= $cutoffTimestamp,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, $unitIndex === 0 ? 0 : 2).' '.$units[$unitIndex];
    }
}
