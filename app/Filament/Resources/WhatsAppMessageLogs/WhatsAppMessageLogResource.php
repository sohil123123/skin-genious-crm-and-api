<?php

namespace App\Filament\Resources\WhatsAppMessageLogs;

use App\Filament\Resources\WhatsAppMessageLogs\Pages;
use App\Models\WhatsAppMessageLog;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use App\Filament\Resources\WhatsAppMessageLogs\Schemas\WhatsAppMessageLogForm;
use App\Filament\Resources\WhatsAppMessageLogs\Tables\WhatsAppMessageLogsTable;

class WhatsAppMessageLogResource extends Resource
{
    protected static ?string $model = WhatsAppMessageLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'WhatsApp Logs';

    protected static ?int $navigationSort = 22;

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return WhatsAppMessageLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppMessageLogsTable::configure($table);
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
            'index' => Pages\ListWhatsAppMessageLogs::route('/'),
            'view' => Pages\ViewWhatsAppMessageLog::route('/{record}'),
        ];
    }
}
