<?php

declare(strict_types=1);

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

/**
 * Covers deleting a lead from the list.
 *
 * The resource drops the soft-delete scope so the trashed filter has something
 * to reveal, which means every other read of that query has to exclude deleted
 * rows itself. The listing, the sidebar badge and global search are the three
 * places where a deleted lead reappearing would be wrong, so they are asserted
 * rather than assumed.
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
        $hasTables = \Illuminate\Support\Facades\Schema::connection($connection)->hasTable('leads');
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

    // A throwaway lead, so a real patient record is never deleted by a test.
    $this->lead = Lead::create([
        'clinic_id' => $clinic->getKey(),
        'full_name' => 'Deletion Test Lead',
        'phone' => '+919999000111',
        'status' => \App\Enums\LeadStatus::New,
        'source' => \App\Enums\LeadSource::Manual,
    ]);
});

afterEach(function (): void {
    $lead = $this->lead ?? null;

    if ($lead !== null) {
        Lead::withTrashed()->whereKey($lead->getKey())->forceDelete();
    }
});

it('soft deletes a lead and can restore it', function (): void {
    Livewire::test(ListLeads::class)
        ->callAction(TestAction::make('delete')->table($this->lead))
        ->assertHasNoActionErrors();

    expect(Lead::find($this->lead->getKey()))->toBeNull()
        ->and(Lead::withTrashed()->find($this->lead->getKey()))->not->toBeNull();

    Livewire::test(ListLeads::class)
        ->callAction(TestAction::make('restore')->table($this->lead));

    expect(Lead::find($this->lead->getKey()))->not->toBeNull();
});

it('permanently deletes a lead', function (): void {
    $this->lead->delete();

    Livewire::test(ListLeads::class)
        ->callAction(TestAction::make('forceDelete')->table($this->lead))
        ->assertHasNoActionErrors();

    expect(Lead::withTrashed()->find($this->lead->getKey()))->toBeNull();
});

it('hides a deleted lead from the listing, the badge and global search', function (): void {
    // The fixture is a New lead, so it is counted by the badge until deleted.
    $badgeWithLead = (int) LeadResource::getNavigationBadge();

    $this->lead->delete();

    // The default listing must not show it despite the dropped scope.
    Livewire::test(ListLeads::class)
        ->assertOk()
        ->assertDontSee('Deletion Test Lead');

    expect(LeadResource::getGlobalSearchEloquentQuery()->whereKey($this->lead->getKey())->exists())
        ->toBeFalse('a deleted lead is findable from the search bar')
        ->and((int) LeadResource::getNavigationBadge())
        ->toBe($badgeWithLead - 1, 'the sidebar badge still counts a deleted lead');
});

it('reveals a deleted lead through the trashed filter', function (): void {
    $this->lead->delete();

    Livewire::test(ListLeads::class)
        ->filterTable('trashed', 'trashed')
        ->assertOk()
        ->assertSee('Deletion Test Lead');
});
