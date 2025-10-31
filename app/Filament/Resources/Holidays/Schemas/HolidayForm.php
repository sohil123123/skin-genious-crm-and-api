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
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

use App\Enums\HolidayStatus;
use App\Enums\HolidayType;

use App\Models\Holiday;
use App\Models\User;

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
                                Grid::make(4)->schema([
                                    // Dynamically add user_id component based on role
                                    auth()->user()->hasRole('therapist')
                                        ? Hidden::make('user_id')->default(auth()->id())
                                        : Select::make('user_id')
                                            ->label('Therapist')
                                            ->relationship('therapists', 'first_name')
                                            ->required(),
                                            // ->afterStateUpdated(function ($state, callable $set) {
                                            //     if ($state) {
                                            //         $user = User::find($state);
                                            //         if ($user && $user->clinic_id) {
                                            //             $set('clinic_id', $user->clinic_id); // Auto-set clinic_id based on selected user
                                            //         }
                                            //     }
                                            // }),

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

                                    Select::make('type')
                                        ->label('Holiday Type')
                                        ->options(HolidayType::class)
                                        ->required(),

                                    ToggleButtons::make('status')
                                        ->visible(!auth()->user()->hasRole('therapist'))
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
                            ->afterStateUpdated(function ($state, $set, $get, $operation) {
                                // Custom validation hook for limits (runs on create/edit)
                                if ($operation === 'create' || $operation === 'edit') {
                                    $userId = $get('user_id');
                                    $type = $get('type');
                                    $startDate = $get('start_date');
                                    $endDate = $get('end_date');

                                    if ($userId && $type && $startDate && $endDate) {
                                        $user = User::find($userId);
                                        $days = (new \DateTime($endDate))->diff(new \DateTime($startDate))->days + 1;
                                        $remaining = $user->remainingLeaveDays($type, date('Y', strtotime($startDate)));

                                        if ($days > $remaining) {
                                            throw ValidationException::withMessages([
                                                'type' => "User has only {$remaining} days remaining for {$type} leave this year.",
                                            ]);
                                        }
                                    }
                                }
                            })
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
