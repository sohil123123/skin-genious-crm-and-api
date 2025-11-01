<?php

namespace App\Filament\Resources\UserLeaveEntitlements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

use App\Enums\HolidayType;

class UserLeaveEntitlementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Basic Information')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('user_id')
                                        ->label('Therapist')
                                        ->relationship('therapists', 'first_name')
                                        ->placeholder('Select Therapist')
                                        ->required(),

                                    Select::make('leave_type')
                                        ->label('Leave Type')
                                        ->options(HolidayType::class)
                                        ->placeholder('Select Leave Type')
                                        ->required(),

                                    TextInput::make('year')
                                        ->required()
                                        ->numeric()
                                        ->placeholder('Enter Year')
                                        ->default(now()->year),

                                    TextInput::make('total_allowed')
                                        ->required()
                                        ->numeric()
                                        ->placeholder('Enter Total Allowed Days')
                                        ->default(0),
                                ])
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Leave Entitlement created date')
                            ->state(fn ($record): ?string => $record->created_at?->diffForHumans()),

                        TextEntry::make('updated_at')
                            ->label('Last modified at')
                            ->state(fn ($record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn ($record) => $record === null),
            ])
            ->columns(3);


    }
}
