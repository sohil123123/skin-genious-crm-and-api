<?php

namespace App\Filament\Resources\WhatsAppMediaLibrary;

use App\Filament\Resources\WhatsAppMediaLibrary\Pages;
use App\Filament\Resources\WhatsAppMediaLibrary\Schemas\WhatsAppMediaLibraryForm;
use App\Filament\Resources\WhatsAppMediaLibrary\Tables\WhatsAppMediaLibraryTable;
use App\Models\WhatsAppMediaLibrary;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsAppMediaLibraryResource extends Resource
{
    protected static ?string $model = WhatsAppMediaLibrary::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?int $navigationSort = 26;

    public static function form(Schema $schema): Schema
    {
        return WhatsAppMediaLibraryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppMediaLibraryTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppMediaLibrary::route('/'),
            'create' => Pages\CreateWhatsAppMediaLibrary::route('/create'),
            'edit' => Pages\EditWhatsAppMediaLibrary::route('/{record}/edit'),
        ];
    }
}
