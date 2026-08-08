<?php

declare(strict_types=1);

use App\DTOs\Lead\ColumnMappingDto;
use App\Filament\Pages\LeadImportWizard;
use App\Models\LeadImport;
use App\Models\User;
use Livewire\Livewire;

/**
 * Renders the wizard's mapping step against the live database.
 *
 * The mapping row uses closures for column spans, label visibility and control
 * visibility, none of which are exercised by simply loading the page — the
 * wizard opens on the upload step. Building the step for real is the only way
 * to know those closures resolve.
 *
 * The suite otherwise runs on an empty in-memory SQLite database, so this test
 * points at the configured MySQL connection and skips itself when the lead
 * tables have not been migrated.
 */
beforeEach(function (): void {
    // phpunit.xml sets DB_DATABASE=:memory: for the SQLite suite, and that env
    // var also feeds the MySQL connection's config, so the real credentials are
    // read straight from .env rather than through env().
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

    // Changing the config alone is not enough once a connection has been
    // resolved, so the manager is purged and the default reassigned.
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

    $this->import = LeadImport::query()->whereNotNull('column_mapping')->latest('id')->first();

    if ($this->import === null) {
        $this->markTestSkipped('No configured import available to render.');
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

function mappingStateFrom(LeadImport $import): array
{
    $rows = [];

    foreach ($import->column_mapping as $definition) {
        $dto = ColumnMappingDto::fromArray($definition);

        $rows[] = [
            'csv_column' => $dto->csvColumn,
            'target' => $dto->target,
            'custom_label' => $dto->customLabel ?? $dto->csvColumn,
            'custom_type' => $dto->customType->value,
            'auto_mapped' => $dto->autoMapped,
            'confidence' => $dto->confidence,
            'suggestions' => $dto->suggestions,
        ];
    }

    return $rows;
}

it('renders the mapping step with every column row', function (): void {
    $rows = mappingStateFrom($this->import);

    $component = Livewire::test(LeadImportWizard::class)
        ->set('importId', $this->import->getKey())
        ->set('data.mapping', $rows)
        ->set('data.settings', $this->import->settings)
        ->set('data.duplicate_strategy', $this->import->duplicate_strategy->value)
        ->set('data.duplicate_match_fields', $this->import->duplicate_match_fields);

    $component->assertOk();

    // Every source column must appear, including the long question headers.
    foreach ($rows as $row) {
        $component->assertSee($row['csv_column'], escape: false);
    }
});

it('shows the label and type controls only for custom questions', function (): void {
    $rows = mappingStateFrom($this->import);

    $customIndexes = [];
    $coreIndexes = [];

    foreach ($rows as $index => $row) {
        str_starts_with($row['target'], ColumnMappingDto::PREFIX_CUSTOM)
            ? $customIndexes[] = $index
            : $coreIndexes[] = $index;
    }

    expect($customIndexes)->not->toBeEmpty('the fixture import has no custom questions')
        ->and($coreIndexes)->not->toBeEmpty('the fixture import has no core mappings');

    $html = Livewire::test(LeadImportWizard::class)
        ->set('importId', $this->import->getKey())
        ->set('data.mapping', $rows)
        ->set('data.settings', $this->import->settings)
        ->set('data.duplicate_strategy', $this->import->duplicate_strategy->value)
        ->set('data.duplicate_match_fields', $this->import->duplicate_match_fields)
        ->html();

    // Counting the rendered field labels rather than the state paths: Livewire
    // serialises every state key into its snapshot whether the control is
    // visible or not, so searching for "mapping.N.custom_type" would match a
    // row that renders no such input at all.
    expect(substr_count($html, 'Answer type'))->toBe(count($customIndexes))
        ->and(substr_count($html, 'Question label'))->toBe(count($customIndexes));

    // Every row keeps an "Import as" label in the markup even where it is
    // visually hidden, because Filament still emits it for screen readers.
    expect(substr_count($html, 'Import as'))->toBeGreaterThanOrEqual(count($rows));
});
