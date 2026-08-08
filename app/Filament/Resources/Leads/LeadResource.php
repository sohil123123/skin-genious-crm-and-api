<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads;

use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Filament\Resources\Leads\Pages\ViewLead;
use App\Filament\Resources\Leads\Schemas\LeadForm;
use App\Filament\Resources\Leads\Schemas\LeadInfolist;
use App\Filament\Resources\Leads\Tables\LeadsTable;
use App\Models\Lead;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $navigationLabel = 'Leads';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return LeadForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'view' => ViewLead::route('/{record}'),
            'edit' => EditLead::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // The soft-delete scope is dropped so the table's trashed filter has
            // something to reveal. The filter hides deleted leads until it is
            // switched, so the default listing is unchanged — but anything else
            // reading this query has to exclude them itself.
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['clinic', 'assignedStaff', 'matchedUser', 'import'])
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic());
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['full_name', 'phone', 'email', 'campaign_name', 'form_name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Lead $record */
        return $record->display_name;
    }

    /**
     * @return array<string, string|null>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Lead $record */
        return [
            'Phone' => $record->phone,
            'Status' => $record->status?->getLabel(),
            'Campaign' => $record->campaign_name,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        // A deleted lead must not be findable from the search bar.
        return static::getEloquentQuery()->whereNull('deleted_at');
    }

    /**
     * Show how many leads still need a first response.
     */
    public static function getNavigationBadge(): ?string
    {
        // getEloquentQuery() no longer excludes deleted leads, and a deleted
        // lead must not keep a badge lit in the sidebar.
        $new = static::getEloquentQuery()->whereNull('deleted_at')->ofStatus(\App\Enums\LeadStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
