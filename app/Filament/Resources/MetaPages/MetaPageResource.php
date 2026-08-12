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
 * Connects Facebook Pages to clinics.
 *
 * A leadgen webhook arrives with no authentication and no clinic — the only
 * identifying value in the payload is page_id. This registry is what turns that
 * into a clinic, so a Page missing from here means its leads cannot be filed.
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('clinic')
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic());
    }
}
