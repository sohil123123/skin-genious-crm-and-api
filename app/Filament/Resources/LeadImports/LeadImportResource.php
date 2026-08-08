<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadImports;

use App\Filament\Resources\LeadImports\Pages\ListLeadImports;
use App\Filament\Resources\LeadImports\Pages\ViewLeadImport;
use App\Filament\Resources\LeadImports\RelationManagers\FailuresRelationManager;
use App\Filament\Resources\LeadImports\RelationManagers\LogsRelationManager;
use App\Filament\Resources\LeadImports\Tables\LeadImportsTable;
use App\Models\LeadImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class LeadImportResource extends Resource
{
    protected static ?string $model = LeadImport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?string $recordTitleAttribute = 'original_filename';

    protected static ?string $navigationLabel = 'Import History';

    protected static ?string $modelLabel = 'lead import';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return LeadImportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FailuresRelationManager::class,
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadImports::route('/'),
            'view' => ViewLeadImport::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // The soft-delete scope is dropped so the table's trashed filter has
            // something to reveal. The filter itself hides deleted records until
            // it is switched, so the default listing is unchanged.
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['clinic', 'uploader', 'template'])
            ->withCount(['failures as unresolved_failures_count' => fn (Builder $query) => $query->where('is_resolved', false)])
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic());
    }

    /**
     * Surface running imports in the sidebar so a queued file is not forgotten.
     */
    public static function getNavigationBadge(): ?string
    {
        // getEloquentQuery() no longer excludes deleted records, and a deleted
        // import must not keep a badge lit in the sidebar.
        $running = static::getEloquentQuery()->whereNull('deleted_at')->running()->count();

        return $running > 0 ? (string) $running : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
