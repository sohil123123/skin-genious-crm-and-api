<?php

namespace App\Filament\Resources\WhatsAppTemplates\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Schema;

class WhatsAppTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Template Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('category')
                            ->maxLength(255),
                        TextInput::make('language')
                            ->required()
                            ->maxLength(255)
                            ->default('en_US'),
                        Select::make('status')
                            ->options([
                                'APPROVED' => 'Approved',
                                'PENDING' => 'Pending',
                                'REJECTED' => 'Rejected',
                            ])
                            ->required()
                            ->default('APPROVED'),
                        KeyValue::make('components')
                            ->label('Template Variables / Components')
                            ->keyLabel('Variable Name (e.g. 1)')
                            ->valueLabel('Default Value')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
