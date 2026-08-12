<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaPages;

use App\Filament\Resources\MetaPages\Pages\ListMetaPages;
use App\Filament\Resources\MetaPages\Schemas\MetaPageForm;
use App\Filament\Resources\MetaPages\Tables\MetaPagesTable;
use App\Models\MetaPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The Pages the CRM has seen leads from.
 *
 * This is a record of what Meta is actually sending, not a configuration step:
 * a Page adds itself the first time one of its leads arrives. Editing exists
 * only for the two optional overrides — pinning a Page to a specific clinic,
 * and switching it off — so nothing here has to be filled in before leads work.
 */
class MetaPageResource extends Resource
{
    protected static ?string $model = MetaPage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?string $recordTitleAttribute = 'page_name';

    protected static ?string $navigationLabel = 'Meta Pages';

    protected static ?string $modelLabel = 'Meta Page';

    protected static ?int $navigationSort = 34;

    public static function form(Schema $schema): Schema
    {
        return MetaPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetaPagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetaPages::route('/'),
        ];
    }

    /**
     * Pages are never created by hand — they arrive from webhooks.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('clinic');

        if (check_role(config('project.roles.super_admin'))) {
            return $query;
        }

        // A self-registered Page has no clinic yet. Scoping it away entirely
        // would hide the very Pages that need a clinic assigned, so they stay
        // visible alongside the current clinic's own.
        return $query->where(fn (Builder $inner) => $inner
            ->whereNull('clinic_id')
            ->orWhere('clinic_id', auth()->user()?->clinic_id));
    }
}
