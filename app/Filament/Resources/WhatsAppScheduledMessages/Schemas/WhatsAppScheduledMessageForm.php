<?php

namespace App\Filament\Resources\WhatsAppScheduledMessages\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppMediaLibrary;

class WhatsAppScheduledMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Recipient & Schedule')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('user_id')
                            ->label('Client')
                            ->searchable()
                            ->preload()
                            ->options(fn () => User::role('client')
                                ->where('is_active', true)
                                ->get()
                                ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' (' . $u->mobile . ')'])
                                ->toArray())
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $user = User::find($state);
                                    if ($user && $user->mobile) {
                                        $set('phone_number', preg_replace('/[^0-9]/', '', $user->mobile));
                                    }
                                }
                            }),

                        TextInput::make('phone_number')
                            ->required()
                            ->label('Phone Number')
                            ->placeholder('e.g. 919876543210'),
                    ]),

                    Grid::make(3)->schema([
                        DateTimePicker::make('scheduled_at')
                            ->required()
                            ->native(false)
                            ->minDate(now()),

                        Select::make('timezone')
                            ->options(['Asia/Kolkata' => 'Asia/Kolkata (IST)', 'UTC' => 'UTC'])
                            ->default('Asia/Kolkata')
                            ->required(),

                        Select::make('type')
                            ->options([
                                'text' => 'Text Message',
                                'template' => 'Template Message',
                                'media' => 'Media Message',
                            ])
                            ->default('text')
                            ->required()
                            ->live(),
                    ]),
                ]),

            Section::make('Message Content')
                ->schema([
                    Select::make('template_name')
                        ->label('WhatsApp Template')
                        ->options(fn () => WhatsAppTemplate::where('status', 'APPROVED')->pluck('name', 'name')->toArray())
                        ->searchable()
                        ->visible(fn ($get) => $get('type') === 'template')
                        ->required(fn ($get) => $get('type') === 'template'),

                    Select::make('media_library_id')
                        ->label('Media File')
                        ->options(fn () => WhatsAppMediaLibrary::pluck('name', 'id')->toArray())
                        ->searchable()
                        ->visible(fn ($get) => $get('type') === 'media')
                        ->required(fn ($get) => $get('type') === 'media'),

                    Textarea::make('content')
                        ->label(fn ($get) => $get('type') === 'media' ? 'Media Caption' : 'Message Body')
                        ->placeholder('Write message text here...')
                        ->rows(4)
                        ->visible(fn ($get) => in_array($get('type'), ['text', 'media']))
                        ->required(fn ($get) => $get('type') === 'text'),
                ]),

            Section::make('Recurrence')
                ->schema([
                    Toggle::make('is_recurring')
                        ->label('Recurring Message')
                        ->live(),

                    Select::make('recurrence_rule')
                        ->label('Send Frequency')
                        ->options([
                            'daily' => 'Daily',
                            'weekly' => 'Weekly',
                            'monthly' => 'Monthly',
                        ])
                        ->visible(fn ($get) => $get('is_recurring'))
                        ->required(fn ($get) => $get('is_recurring')),
                ]),
        ]);
    }
}
