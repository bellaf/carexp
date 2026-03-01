<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('backups');

    config([
        'backup.backup.destination.disks' => ['backups'],
        'backup.backup.name' => 'carexp',
        'backup.monitor_backups.0.health_checks' => [
            \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
            \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
        ],
    ]);
});

test('admin can view backup settings page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('backup.edit'))
        ->assertOk()
        ->assertSee('Backups')
        ->assertSee('Database Backups');
});

test('non admin cannot view backup settings page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('backup.edit'))
        ->assertForbidden();
});

test('backup settings page lists recent backup archives', function () {
    $admin = User::factory()->admin()->create();

    Storage::disk('backups')->put('carexp/carexp-2026-03-01-020000.zip', 'backup-one');
    Storage::disk('backups')->put('carexp/carexp-2026-03-02-020000.zip', 'backup-two');

    $this->actingAs($admin)
        ->get(route('backup.edit'))
        ->assertOk()
        ->assertSee('carexp-2026-03-01-020000.zip')
        ->assertSee('carexp-2026-03-02-020000.zip');
});

test('admin can trigger manual database backup from settings page', function () {
    $admin = User::factory()->admin()->create();

    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ])
        ->andReturn(0);

    Artisan::shouldReceive('output')
        ->once()
        ->andReturn('Backup complete');

    $this->actingAs($admin);

    Livewire::test('pages::settings.backup')
        ->call('runBackup')
        ->assertSee('Database backup completed.')
        ->assertSee('Backup complete');
});
