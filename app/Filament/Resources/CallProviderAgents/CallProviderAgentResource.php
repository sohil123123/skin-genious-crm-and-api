<?php

declare(strict_types=1);

namespace App\Filament\Resources\CallProviderAgents;

use App\Filament\Resources\CallProviderAgents\Pages\ListCallProviderAgents;
use App\Filament\Resources\CallProviderAgents\Schemas\CallProviderAgentForm;
use App\Filament\Resources\CallProviderAgents\Tables\CallProviderAgentsTable;
use App\Models\CallProviderAgent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The screen that turns "some calls have no agent" into a short to-do list.
 *
 * Rows appear here on their own: the first time an unrecognised number places a
 * call, the resolver records it rather than dropping the attribution. Each
 * unmapped row is calls being logged against a person the CRM cannot name, and
 * every agent performance figure is wrong by exactly that much until somebody
 * fills it in.
 */
class CallProviderAgentResource extends Resource
{
    protected static ?string $model = CallProviderAgent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Calls';

    protected static ?string $navigationLabel = 'Agent Mapping';

    protected static ?string $modelLabel = 'agent mapping';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return CallProviderAgentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallProviderAgentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCallProviderAgents::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user:id,first_name,last_name', 'clinic:id,name']);
    }

    /**
     * How many provider identities nobody has claimed yet.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->needsMapping()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Provider agents not yet mapped to a CRM user';
    }
}
