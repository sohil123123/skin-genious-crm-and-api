<?php

namespace App\Filament\Resources\WhatsAppTemplates;

use App\Filament\Resources\WhatsAppTemplates\Pages;
use App\Models\WhatsAppTemplate;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use App\Filament\Resources\WhatsAppTemplates\Schemas\WhatsAppTemplateForm;
use App\Filament\Resources\WhatsAppTemplates\Tables\WhatsAppTemplatesTable;

class WhatsAppTemplateResource extends Resource
{
    protected static ?string $model = WhatsAppTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Others';

    protected static ?string $navigationLabel = 'WhatsApp Templates';

    protected static ?int $navigationSort = 22;

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return WhatsAppTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppTemplates::route('/'),
            'create' => Pages\CreateWhatsAppTemplate::route('/create'),
            'edit' => Pages\EditWhatsAppTemplate::route('/{record}/edit'),
        ];
    }
}
