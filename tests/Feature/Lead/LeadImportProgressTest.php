<?php

declare(strict_types=1);

use App\Enums\LeadImportStatus;
use App\Filament\Resources\LeadImports\Pages\ViewLeadImport;
use App\Models\LeadImport;
use App\Models\User;
use Livewire\Livewire;

/**
 * Covers the live progress panel on the import view page.
 *
 * The panel's job is to move while a job is running, and the only way it can is
 * by re-reading the row on each poll — the page hydrates its record once on
 * mount and would otherwise re-render the counters as they stood at page load.
 * That re-read happens inside a viewData closure, so nothing but rendering the
 * page for real exercises it.
 *
 * Like the mapping test, this points at the configured MySQL connection and
 * skips itself when the lead tables have not been migrated.
 */
beforeEach(function (): void {
    $dotenv = \Dotenv\Dotenv::createArrayBacked(base_path())->safeLoad();
    $connection = $dotenv['DB_CONNECTION'] ?? 'mysql';

    config([
        "database.connections.{$connection}.database" => $dotenv['DB_DATABASE'] ?? null,
        "database.connections.{$connection}.host" => $dotenv['DB_HOST'] ?? '127.0.0.1',
        "database.connections.{$connection}.port" => $dotenv['DB_PORT'] ?? '3306',
        "database.connections.{$connection}.username" => $dotenv['DB_USERNAME'] ?? 'root',
        "database.connections.{$connection}.password" => $dotenv['DB_PASSWORD'] ?? '',
        'database.default' => $connection,
    ]);

    \Illuminate\Support\Facades\DB::purge($connection);
    \Illuminate\Support\Facades\DB::setDefaultConnection($connection);

    try {
        $hasTables = \Illuminate\Support\Facades\Schema::connection($connection)->hasTable('lead_imports');
    } catch (Throwable) {
        $hasTables = false;
    }

    if (! $hasTables) {
        $this->markTestSkipped('Lead tables are not migrated on the default connection.');
    }

    $this->import = LeadImport::query()->latest('id')->first();

    if ($this->import === null) {
        $this->markTestSkipped('No import available to render.');
    }

    $admin = User::query()
        ->withoutGlobalScopes()
        ->whereHas('roles', fn ($query) => $query->where('name', config('project.roles.super_admin')))
        ->first();

    if ($admin === null) {
        $this->markTestSkipped('No super admin available.');
    }

    $this->actingAs($admin);
});

/**
 * Put the import into a running state without disturbing its real counters, and
 * restore it once the assertion has been made.
 */
function withRunningImport(LeadImport $import, int $processed, callable $callback): void
{
    $original = $import->only([
        'status', 'total_rows', 'processed_rows', 'imported_rows', 'started_at', 'finished_at',
    ]);

    $import->forceFill([
        'status' => LeadImportStatus::Processing->value,
        'total_rows' => 100,
        'processed_rows' => $processed,
        'imported_rows' => $processed,
        'started_at' => now()->subSeconds(10),
        'finished_at' => null,
    ])->save();

    try {
        $callback();
    } finally {
        $import->forceFill($original)->save();
    }
}

it('polls the progress panel while the import is running', function (): void {
    withRunningImport($this->import, 25, function (): void {
        Livewire::test(ViewLeadImport::class, ['record' => $this->import->getKey()])
            ->assertOk()
            // wire:poll is what makes the panel live at all; without it the bar
            // only ever moves on a manual refresh.
            ->assertSee('wire:poll.2s', escape: false)
            ->assertSee('25 of 100 rows');
    });
});

it('shows counters written after the page was mounted', function (): void {
    withRunningImport($this->import, 25, function (): void {
        $component = Livewire::test(ViewLeadImport::class, ['record' => $this->import->getKey()])
            ->assertSee('25 of 100 rows');

        // Stands in for the job advancing between two polls.
        LeadImport::query()->whereKey($this->import->getKey())->update([
            'processed_rows' => 60,
            'imported_rows' => 60,
        ]);

        $component->call('$refresh')
            ->assertSee('60 of 100 rows')
            ->assertDontSee('25 of 100 rows');
    });
});

it('stops polling once the import has finished', function (): void {
    $this->import->forceFill([
        'status' => LeadImportStatus::Completed->value,
        'finished_at' => now(),
    ])->save();

    Livewire::test(ViewLeadImport::class, ['record' => $this->import->getKey()])
        ->assertOk()
        ->assertDontSee('wire:poll', escape: false);
});
