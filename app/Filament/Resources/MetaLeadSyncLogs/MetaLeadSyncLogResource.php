<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaLeadSyncLogs;

use App\Enums\MetaSyncStatus;
use App\Filament\Resources\MetaLeadSyncLogs\Pages\ListMetaLeadSyncLogs;
use App\Filament\Resources\MetaLeadSyncLogs\Schemas\MetaLeadSyncLogInfolist;
use App\Filament\Resources\MetaLeadSyncLogs\Tables\MetaLeadSyncLogsTable;
use App\Models\MetaLeadSyncLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only view of what the Meta integration has done.
 *
 * This exists for one question that the application log cannot answer at a
 * glance: which leads did not make it in, and why. Application and system
 * errors continue to go to the log viewer through the meta_leads channel — this
 * deliberately does not duplicate that.
 *
 * Records are never created or edited by hand; the only write is Retry.
 */
class MetaLeadSyncLogResource extends Resource
{
    protected static ?string $model = MetaLeadSyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?string $navigationLabel = 'Meta Sync Log';

    protected static ?string $modelLabel = 'Meta sync record';

    protected static ?int $navigationSort = 36;

    public static function infolist(Schema $schema): Schema
    {
        return MetaLeadSyncLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetaLeadSyncLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetaLeadSyncLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Surface how many leads currently need attention.
     */
    public static function getNavigationBadge(): ?string
    {
        $failed = static::getEloquentQuery()
            ->where('status', MetaSyncStatus::Failed->value)
            ->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['metaPage.clinic', 'lead'])
            ->when(!check_role(config('project.roles.super_admin')), fn(Builder $query) => $query->forCurrentClinic());
    }
}
