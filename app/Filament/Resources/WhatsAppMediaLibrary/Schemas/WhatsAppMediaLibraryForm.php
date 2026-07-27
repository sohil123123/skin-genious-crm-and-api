<?php

namespace App\Filament\Resources\WhatsAppMediaLibrary\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsAppMediaLibraryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Media Details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Descriptive name for this media'),

                    Select::make('type')
                        ->options([
                            'image' => '📷 Image',
                            'video' => '🎥 Video',
                            'document' => '📄 Document',
                            'audio' => '🎵 Audio',
                        ])
                        ->required(),

                    FileUpload::make('file_path')
                        ->label('File')
                        ->required()
                        ->disk('public')
                        ->directory('whatsapp-media-library')
                        ->maxSize(16384) // 16MB
                        ->acceptedFileTypes([
                            'image/jpeg', 'image/png', 'image/webp',
                            'video/mp4', 'video/3gpp',
                            'audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/ogg',
                            'application/pdf',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ]),
                ]),
        ]);
    }
}
