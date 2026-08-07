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
            ->with(['clinic', 'uploader', 'template'])
            ->withCount(['failures as unresolved_failures_count' => fn (Builder $query) => $query->where('is_resolved', false)])
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic());
    }

    /**
     * Surface running imports in the sidebar so a queued file is not forgotten.
     */
    public static function getNavigationBadge(): ?string
    {
        $running = static::getEloquentQuery()->running()->count();

        return $running > 0 ? (string) $running : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
