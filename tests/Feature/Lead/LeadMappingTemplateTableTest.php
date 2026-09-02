<?php

declare(strict_types=1);

use App\Filament\Resources\LeadMappingTemplates\Pages\ListLeadMappingTemplates;
use App\Models\LeadMappingTemplate;
use App\Models\User;
use Livewire\Livewire;

/**
 * Covers the Columns count on the mapping templates table.
 *
 * Badging an array state makes Filament render one badge per element and run
 * the formatter against each element rather than the array, which is how the
 * column came to show one pill reading "0" per saved header. Only rendering the
 * table catches it — the column configures cleanly either way.
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
        $hasTables = \Illuminate\Support\Facades\Schema::connection($connection)->hasTable('lead_mapping_templates');
    } catch (Throwable) {
        $hasTables = false;
    }

    if (! $hasTables) {
        $this->markTestSkipped('Lead tables are not migrated on the default connection.');
    }

    $this->template = LeadMappingTemplate::query()
        ->get()
        ->first(fn (LeadMappingTemplate $template): bool => ($template->header_columns ?? []) !== []);

    if ($this->template === null) {
        $this->markTestSkipped('No template with saved headers available to render.');
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

it('shows how many columns the template records', function (): void {
    $expected = count($this->template->header_columns);

    expect($expected)->toBeGreaterThan(0);

    Livewire::test(ListLeadMappingTemplates::class)
        ->assertOk()
        ->assertTableColumnStateSet('header_columns', $expected, record: $this->template);
});

it('lists the column names in the tooltip', function (): void {
    Livewire::test(ListLeadMappingTemplates::class)
        ->assertOk()
        ->assertSee(implode(', ', $this->template->header_columns), escape: false);
});
