<?php

namespace App\Filament\Resources\WhatsAppScheduledMessages;

use App\Filament\Resources\WhatsAppScheduledMessages\Pages;
use App\Filament\Resources\WhatsAppScheduledMessages\Schemas\WhatsAppScheduledMessageForm;
use App\Filament\Resources\WhatsAppScheduledMessages\Tables\WhatsAppScheduledMessagesTable;
use App\Models\WhatsAppScheduledMessage;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsAppScheduledMessageResource extends Resource
{
    protected static ?string $model = WhatsAppScheduledMessage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Scheduled Messages';

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return WhatsAppScheduledMessageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppScheduledMessagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppScheduledMessages::route('/'),
            'create' => Pages\CreateWhatsAppScheduledMessage::route('/create'),
            'edit' => Pages\EditWhatsAppScheduledMessage::route('/{record}/edit'),
        ];
    }
}
