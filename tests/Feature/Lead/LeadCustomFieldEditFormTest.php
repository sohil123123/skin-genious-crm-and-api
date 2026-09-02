<?php

declare(strict_types=1);

use App\Enums\LeadFieldType;
use App\Filament\Resources\LeadCustomFields\Pages\ListLeadCustomFields;
use App\Models\LeadCustomField;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Renders the edit modal for one imported question.
 *
 * The form leans on closures for section visibility, the raw-label reference
 * line and the humanised options preview, none of which run until the modal is
 * actually mounted against a record.
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
        ->whereNotNull('options')
        ->get()
        ->first(fn (LeadCustomField $field): bool => ($field->options ?? []) !== []
            && in_array($field->type, [LeadFieldType::Select, LeadFieldType::MultiSelect], true));

    if ($this->field === null) {
        $this->markTestSkipped('No choice question with options available to render.');
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

function editAction(LeadCustomField $field): TestAction
{
    return TestAction::make('edit')->table($field);
}

it('mounts the edit form with the record loaded', function (): void {
    Livewire::test(ListLeadCustomFields::class)
        ->mountAction(editAction($this->field))
        ->assertActionMounted(editAction($this->field))
        ->assertHasNoActionErrors()
        ->assertActionDataSet([
            'label' => $this->field->label,
            'type' => $this->field->type->value,
            'options' => $this->field->options,
        ]);
});

it('titles the modal with the humanised question, not the raw key', function (): void {
    Livewire::test(ListLeadCustomFields::class)
        ->mountAction(editAction($this->field))
        ->assertSee($this->field->display_label, escape: false);
});

it('saves an edited question', function (): void {
    $original = $this->field->label;

    try {
        Livewire::test(ListLeadCustomFields::class)
            ->mountAction(editAction($this->field))
            ->setActionData(['label' => 'Which session are you interested in?'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($this->field->fresh()->label)->toBe('Which session are you interested in?')
            // The key backs every stored answer, so editing the text must not
            // disturb it.
            ->and($this->field->fresh()->key)->toBe($this->field->key);
    } finally {
        $this->field->forceFill(['label' => $original])->save();
    }
});
