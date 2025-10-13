<?php

namespace App\Filament\Resources\Holidays\Schemas;

use Filament\Schemas\Schema;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Hidden;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

use Illuminate\Support\Facades\Auth;
use App\Enums\HolidayStatus;
use Illuminate\Support\Carbon;

use App\Models\Holiday;

class HolidayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Basic Information')
                            // ->description('Location and mapping information.')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Grid::make(3)->schema([
                                    // Dynamically add user_id component based on role
                                    auth()->user()->hasRole('therapist')
                                        ? Hidden::make('user_id')->default(auth()->id())
                                        : Select::make('user_id')
                                            ->label('Therapist')
                                            ->relationship('therapists', 'first_name')
                                            ->required(), // No default for admins/managers

                                    DatePicker::make('start_date')
                                        ->required()
                                        ->closeOnDateSelection()
                                        ->native(false)
                                        ->minDate(Carbon::today())
                                        ->maxDate(fn ($get) => $get('end_date'))
                                        ->reactive()
                                        ->placeholder('Select start date'),

                                    DatePicker::make('end_date')
                                        ->required()
                                        ->minDate(fn ($get) => $get('start_date') ?? Carbon::today())
                                        ->closeOnDateSelection()
                                        ->native(false)
                                        ->afterOrEqual('start_date')
                                        ->reactive()
                                        ->placeholder('Select end date'),

                                    // Dynamically add status component based on role
                                    auth()->user()->hasRole('therapist')
                                        ? Hidden::make('status')->default('pending')
                                        : ToggleButtons::make('status')
                                            ->inline()
                                            ->options(HolidayStatus::class)
                                            ->default('pending')
                                            ->required()
                                            ->columnSpan(['lg' => 2]),
                                ]),
                                Grid::make(2)->schema([
                                    Textarea::make('reason')->rows(4)->placeholder('Reason for holiday')->required(),
                                ])
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => fn (?Holiday $record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Holiday created date')
                            ->state(fn (Holiday $record): ?string => $record->created_at?->diffForHumans()),

                        TextEntry::make('updated_at')
                            ->label('Last modified at')
                            ->state(fn (Holiday $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?Holiday $record) => $record === null),
            ])
            ->columns(3);


    }
}
