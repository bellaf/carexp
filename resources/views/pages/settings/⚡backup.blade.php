<?php

use App\Support\BackupStatusReporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?string $commandStatus = null;
    public ?string $commandOutput = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->canManageUsers(), 403);
    }

    public function runBackup(): void
    {
        $this->ensureAdmin();

        $exitCode = Artisan::call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ]);

        $this->setCommandFeedback($exitCode, 'Database backup completed.', 'Database backup failed.');
    }

    public function cleanBackups(): void
    {
        $this->ensureAdmin();

        $exitCode = Artisan::call('backup:clean', [
            '--disable-notifications' => true,
        ]);

        $this->setCommandFeedback($exitCode, 'Backup cleanup completed.', 'Backup cleanup failed.');
    }

    public function monitorBackups(): void
    {
        $this->ensureAdmin();

        $exitCode = Artisan::call('backup:monitor');

        $this->setCommandFeedback($exitCode, 'Backup health check completed.', 'Backup health check reported a problem.');
    }

    #[Computed]
    public function backupSummary(): array
    {
        return app(BackupStatusReporter::class)->summary();
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->canManageUsers(), 403);
    }

    private function setCommandFeedback(int $exitCode, string $successMessage, string $failureMessage): void
    {
        $this->commandStatus = $exitCode === 0 ? $successMessage : $failureMessage;
        $this->commandOutput = trim(Artisan::output()) ?: null;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Backups')" :subheading="__('Admin-only database backup controls and status')" max-width="max-w-4xl">
        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <flux:heading>{{ __('Database Backups') }}</flux:heading>
                        <flux:subheading>{{ __('This page manages database-only backups using Spatie Backup. It does not create a full application restore archive.') }}</flux:subheading>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <flux:button variant="primary" wire:click="runBackup" wire:loading.attr="disabled" wire:target="runBackup">
                            {{ __('Run Backup Now') }}
                        </flux:button>
                        <flux:button variant="ghost" wire:click="cleanBackups" wire:loading.attr="disabled" wire:target="cleanBackups">
                            {{ __('Clean Old Backups') }}
                        </flux:button>
                        <flux:button variant="ghost" wire:click="monitorBackups" wire:loading.attr="disabled" wire:target="monitorBackups">
                            {{ __('Run Health Check') }}
                        </flux:button>
                    </div>
                </div>

                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Scheduled commands run at 02:00 (backup), 02:30 (cleanup), and 03:00 (health check) via the Laravel scheduler.') }}
                </flux:text>

                @if ($commandStatus !== null)
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/50">
                        <div class="font-medium">{{ $commandStatus }}</div>
                        @if ($commandOutput !== null)
                            <pre class="mt-3 overflow-x-auto whitespace-pre-wrap text-xs text-zinc-600 dark:text-zinc-300">{{ $commandOutput }}</pre>
                        @endif
                    </div>
                @endif
            </flux:card>

            <div class="grid gap-4 md:grid-cols-4">
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Status') }}</flux:text>
                    <flux:heading>{{ $this->backupSummary['is_healthy'] ? __('Healthy') : __('Attention') }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Max age') }}: {{ $this->backupSummary['maximum_age_days'] }} {{ __('day(s)') }}
                    </flux:text>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Backup Count') }}</flux:text>
                    <flux:heading>{{ $this->backupSummary['total_backups'] }}</flux:heading>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Disk') }}</flux:text>
                    <flux:heading>{{ $this->backupSummary['disk'] }}</flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $this->backupSummary['root'] }}</flux:text>
                </flux:card>
                <flux:card class="space-y-1">
                    <flux:text>{{ __('Latest Backup') }}</flux:text>
                    <flux:heading>
                        {{ $this->backupSummary['latest_backup']['last_modified'] ?? __('Never') }}
                    </flux:heading>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $this->backupSummary['latest_backup']['size'] ?? __('N/A') }}
                    </flux:text>
                </flux:card>
            </div>

            <flux:card class="space-y-3">
                <flux:heading>{{ __('Recent Backups') }}</flux:heading>

                @if ($this->backupSummary['recent_backups'] === [])
                    <flux:text>{{ __('No backup archives were found on the configured backup disk.') }}</flux:text>
                @else
                    <div class="space-y-3 md:hidden">
                        @foreach ($this->backupSummary['recent_backups'] as $backup)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <div class="font-medium">{{ $backup['name'] }}</div>
                                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $backup['path'] }}</div>
                                <div class="mt-3 flex items-center justify-between text-sm">
                                    <span>{{ $backup['size'] }}</span>
                                    <span>{{ $backup['last_modified'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700 md:block">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-50 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                <tr>
                                    <th class="px-3 py-2 font-medium">{{ __('File') }}</th>
                                    <th class="px-3 py-2 font-medium">{{ __('Path') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Size') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('Modified') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->backupSummary['recent_backups'] as $backup)
                                    <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                        <td class="px-3 py-2">{{ $backup['name'] }}</td>
                                        <td class="px-3 py-2 text-zinc-500 dark:text-zinc-400">{{ $backup['path'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $backup['size'] }}</td>
                                        <td class="px-3 py-2 text-right">{{ $backup['last_modified'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        </div>
    </x-pages::settings.layout>
</section>
