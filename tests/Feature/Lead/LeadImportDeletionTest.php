<?php

declare(strict_types=1);

use App\Filament\Resources\LeadImports\Pages\ListLeadImports;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Covers deleting an import record from the history.
 *
 * The point worth protecting is that neither delete touches the patients: the
 * leads table holds lead_import_id as nullOnDelete, so imported leads survive
 * and simply stop pointing at a batch. A schema change that made that cascade
 * would quietly destroy patient records on a permanent delete, and this is what
 * would catch it.
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

    $admin = User::query()
        ->withoutGlobalScopes()
        ->whereHas('roles', fn ($query) => $query->where('name', config('project.roles.super_admin')))
        ->first();

    if ($admin === null) {
        $this->markTestSkipped('No super admin available.');
    }

    $this->actingAs($admin);

    $clinic = \App\Models\Clinic::query()->active()->first();

    if ($clinic === null) {
        $this->markTestSkipped('No active clinic available.');
    }

    // A throwaway record, so a real import in the history is never deleted.
    $this->import = LeadImport::create([
        'clinic_id' => $clinic->getKey(),
        'uploaded_by' => $admin->getKey(),
        'original_filename' => 'deletion-test.csv',
        'stored_path' => 'lead-imports/deletion-test-does-not-exist.csv',
        'disk' => (string) config('leads.storage.disk', 'local'),
        'status' => \App\Enums\LeadImportStatus::Completed,
    ]);
});

afterEach(function (): void {
    // beforeEach may have skipped before creating the fixture, and afterEach
    // still runs in that case.
    $import = $this->import ?? null;

    if ($import !== null) {
        LeadImport::withTrashed()->whereKey($import->getKey())->forceDelete();
    }
});

it('soft deletes an import record and can restore it', function (): void {
    Livewire::test(ListLeadImports::class)
        ->callAction(TestAction::make('delete')->table($this->import))
        ->assertHasNoActionErrors();

    expect(LeadImport::find($this->import->getKey()))->toBeNull()
        ->and(LeadImport::withTrashed()->find($this->import->getKey()))->not->toBeNull();

    Livewire::test(ListLeadImports::class)
        ->callAction(TestAction::make('restore')->table($this->import));

    expect(LeadImport::find($this->import->getKey()))->not->toBeNull();
});

it('permanently deletes an import record', function (): void {
    $this->import->delete();

    Livewire::test(ListLeadImports::class)
        ->callAction(TestAction::make('forceDelete')->table($this->import))
        ->assertHasNoActionErrors();

    expect(LeadImport::withTrashed()->find($this->import->getKey()))->toBeNull();
});

it('keeps the imported leads when the import is permanently deleted', function (): void {
    $lead = Lead::query()->first();

    if ($lead === null) {
        $this->markTestSkipped('No lead available to check against.');
    }

    // Point a real lead at the throwaway import for the duration of the test,
    // so the deletion has something to cascade to if the schema ever changes.
    $originalImportId = $lead->lead_import_id;
    $lead->forceFill(['lead_import_id' => $this->import->getKey()])->save();

    try {
        $this->import->delete();

        Livewire::test(ListLeadImports::class)
            ->callAction(TestAction::make('forceDelete')->table($this->import))
            ->assertHasNoActionErrors();

        // The patient survives; only the link to the batch is gone. A fresh
        // query rather than refresh(), which would fail outright on a deleted
        // row and obscure what happened.
        $survivor = Lead::query()->whereKey($lead->getKey())->first();

        expect($survivor)->not->toBeNull('the lead was destroyed with the import')
            ->and($survivor->lead_import_id)->toBeNull();
    } finally {
        Lead::query()->whereKey($lead->getKey())
            ->update(['lead_import_id' => $originalImportId]);
    }
});
