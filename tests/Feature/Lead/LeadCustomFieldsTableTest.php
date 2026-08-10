<?php

declare(strict_types=1);

use App\Filament\Resources\LeadCustomFields\Pages\ListLeadCustomFields;
use App\Models\LeadCustomField;
use App\Models\User;
use Livewire\Livewire;

/**
 * Covers the Options column on the lead questions table.
 *
 * Badging an array state makes Filament render one badge per element and run
 * the formatter against each element rather than the array, which is how the
 * column came to show a row of blank pills. Only rendering the table for real
 * catches that, since the column configures cleanly either way.
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
        $hasTables = \Illuminate\Support\Facades\Schema::connection($connection)->hasTable('lead_custom_fields');
    } catch (Throwable) {
        $hasTables = false;
    }

    if (! $hasTables) {
        $this->markTestSkipped('Lead tables are not migrated on the default connection.');
    }

    $this->field = LeadCustomField::query()
        ->where('is_active', true)
        ->whereNotNull('options')
        ->get()
        ->first(fn (LeadCustomField $field): bool => ($field->options ?? []) !== []);

    if ($this->field === null) {
        $this->markTestSkipped('No question with options available to render.');
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

it('renders the option values as badges', function (): void {
    $component = Livewire::test(ListLeadCustomFields::class)->assertOk();

    // Only the first few are shown before the list is truncated, and that is
    // the part the column promises to render.
    $shown = array_slice($this->field->options, 0, 3);

    foreach ($shown as $option) {
        $component->assertSee(
            \Illuminate\Support\Str::limit(LeadCustomField::humanizeValue((string) $option), 40),
            escape: false,
        );
    }
});

it('does not render the raw snake_cased option values', function (): void {
    $option = (string) $this->field->options[0];

    if (! str_contains($option, '_')) {
        $this->markTestSkipped('The first option is not snake_cased.');
    }

    Livewire::test(ListLeadCustomFields::class)
        ->assertOk()
        ->assertDontSee($option, escape: false);
});
