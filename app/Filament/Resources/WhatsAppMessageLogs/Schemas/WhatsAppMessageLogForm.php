<?php

namespace App\Filament\Resources\WhatsAppMessageLogs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Schema;

class WhatsAppMessageLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->disabled(),
                TextInput::make('phone_number')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                TextInput::make('template_name')
                    ->maxLength(255)
                    ->disabled(),
                TextInput::make('message_id')
                    ->maxLength(255)
                    ->disabled(),
                TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                DateTimePicker::make('sent_at')
                    ->disabled(),
                KeyValue::make('error_response')
                    ->columnSpanFull()
                    ->disabled(),
            ]);
    }
}
