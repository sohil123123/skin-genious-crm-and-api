<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls;

use App\Filament\Resources\Calls\Pages\ListCalls;
use App\Filament\Resources\Calls\Pages\ViewCall;
use App\Filament\Resources\Calls\Schemas\CallInfolist;
use App\Filament\Resources\Calls\Tables\CallsTable;
use App\Models\Call;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Every call the clinic has made or received, from either provider.
 *
 * Read-mostly on purpose. A call record is an account of something that
 * happened on a telephone, and staff should be correcting who it was with and
 * what came of it — not editing when it started or how long it lasted. There is
 * no create page and no edit page: the only writes are the outcome, the note,
 * the follow-up and the customer match, each through its own action.
 */
class CallResource extends Resource
{
    protected static ?string $model = Call::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone';

    protected static string|UnitEnum|null $navigationGroup = 'Calls';

    protected static ?string $navigationLabel = 'Calls';

    protected static ?string $recordTitleAttribute = 'client_phone_normalized';

    protected static ?int $navigationSort = 10;

    public static function infolist(Schema $schema): Schema
    {
        return CallInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalls::route('/'),
            'view' => ViewCall::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // Dropped so the table's trashed filter has something to reveal.
            // The filter hides deleted calls until it is switched, so the
            // default listing is unchanged.
            ->withoutGlobalScopes([SoftDeletingScope::class])
            // Eager loaded because the call list renders the customer, the
            // agent and the clinic on every row, and a call table without this
            // is the textbook N+1.
            // recordings is loaded for the table's player column. Without it
            // that column fires a query per row, which on a deferred table of a
            // few hundred calls is the difference between one page load and
            // several hundred.
            ->with([
                'customer:id,first_name,last_name,mobile',
                'lead:id,full_name,phone',
                'agent:id,first_name,last_name',
                'clinic:id,name',
                'recordings:id,call_id,storage_disk,storage_path,storage_status,duration_seconds,file_size,extension,error_message',
            ])
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic());
    }

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['client_phone_normalized', 'client_name', 'provider_call_id', 'employee_name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Call $record */
        return $record->customer_name;
    }

    /**
     * @return array<string, string|null>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Call $record */
        return [
            'Direction' => $record->direction?->getLabel(),
            'Status' => $record->call_status?->getLabel(),
            'When' => $record->started_at?->timezone(app_timezone())->format(app_datetime_format()),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getEloquentQuery()->whereNull('deleted_at');
    }

    /**
     * How many calls happened today.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereNull('deleted_at')
            // started_at is when the conversation actually happened, and it is
            // what the list sorts by, so the badge and the first page of the
            // table agree with each other.
            ->whereDate('started_at', now()->toDateString())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Calls today';
    }
}
