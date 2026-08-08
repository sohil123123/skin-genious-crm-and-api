<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadCustomFields;

use App\Filament\Resources\LeadCustomFields\Pages\ListLeadCustomFields;
use App\Filament\Resources\LeadCustomFields\Schemas\LeadCustomFieldForm;
use App\Filament\Resources\LeadCustomFields\Tables\LeadCustomFieldsTable;
use App\Models\LeadCustomField;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;


/**
 * Manages the registry of dynamic lead-form questions.
 *
 * The important operation here is Merge: Facebook forms routinely ask the same
 * question in slightly different words across campaigns, and without a way to
 * fold two entries into one, reporting on that question quietly splits.
 */
class LeadCustomFieldResource extends Resource
{
    protected static ?string $model = LeadCustomField::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $navigationLabel = 'Lead Questions';

    protected static ?string $modelLabel = 'lead question';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return LeadCustomFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadCustomFieldsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeadCustomFields::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('clinic')
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic());
    }
}
