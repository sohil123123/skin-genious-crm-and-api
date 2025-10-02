<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Actions\Action;

use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
// use App\Enums\UserStatus;
use Filament\Support\Icons\Heroicon;

use App\Models\User;
use App\Models\Role;
use Filament\Forms\Get;

class UserForm
{
    public static function getPersonalInformationComponents(): array
    {
        return [
            Grid::make(4)->schema([
                TextInput::make('first_name')->required()->placeholder('First Name'),
                TextInput::make('last_name')->required()->placeholder('Last Name'),
                Select::make('gender')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                        'Other' => 'Other',
                    ])
                    ->required(),
                DatePicker::make('date_of_birth'),
            ]),
        ];
    }

    public static function getContactDetailsComponents(): array
    {
        return [
            Grid::make(3)->schema([
                TextInput::make('mobile')->required()->tel()->placeholder('Mobile Number'),
                TextInput::make('email')->label('Email address')->email()->placeholder('Email Address'),
                TextInput::make('occupation')->placeholder('Occupation'),
            ]),
            Grid::make(3)->schema([
                TextInput::make('city')->placeholder('City'),
                TextInput::make('pincode')->placeholder('Pincode'),
                TextInput::make('referral_code')
                    ->default(fn (string $context) => $context === 'create'
                        ? 'REF' . random_int(100000, 999999)
                        : context()->record?->referral_code
                    )
                    ->disabled() // user cannot change
                    ->dehydrated() // still save value
                    ->required(fn (string $context) => $context === 'create') // required only on create
                    ->maxLength(32)
                    ->unique(User::class, 'referral_code', ignoreRecord: true),
            ]),
            Grid::make(2)->schema([
                TextInput::make('address_line_1')->placeholder('Address Line 1'),
                TextInput::make('address_line_2')->placeholder('Address Line 2'),
            ])


        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Personal Information')
                            ->schema(static::getPersonalInformationComponents())
                            ->collapsible(),

                        Section::make('Contact Details')
                            ->schema(static::getContactDetailsComponents())
                            ->collapsible(),

                        Section::make('Account Settings')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('how_did_you_hear')
                                        ->options([
                                            'Skin Genius' => 'Skin genius',
                                            'Social Media' => 'Social media',
                                            'Friend Referral' => 'Friend referral',
                                            'Google Search' => 'Google search',
                                            'Practo/Lybrate' => 'Practo/lybrate',
                                            'By Doctor' => 'By doctor',
                                            'Other' => 'Other',
                                        ]),

                                    ToggleButtons::make('opt_for_loyalty')
                                        ->inline()
                                        ->label('Opted for Loyalty Program?')
                                        ->default(false)
                                        ->boolean(),

                                    ToggleButtons::make('is_active')
                                        ->inline()
                                        ->boolean()
                                        ->default(fn ($record) => $record?->is_active ?? true)
                                        ->required(),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('password')
                                        ->password()
                                        ->revealable()
                                        ->placeholder('Password')
                                        ->required(fn (string $context): bool => $context === 'create')
                                        ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                                        ->dehydrated(fn ($state) => filled($state))
                                ]),
                            ])
                            ->collapsible(),

                        Section::make('Roles & Permissions')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('roles')
                                        ->relationship('roles', 'name')
                                        ->multiple()
                                        ->preload()
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($set, ?array $state) {
                                            if (!empty($state)) {
                                                $selectedRoles = Role::whereIn('id', $state)->pluck('name')->toArray();
                                                if (!(in_array('Clinic_manager', $selectedRoles) || in_array('User', $selectedRoles))) {
                                                    $set('clinic_id', null);
                                                }
                                            } else {
                                                $set('clinic_id', null);
                                            }
                                        }),

                                    Select::make('clinic_id')
                                        ->label('Assigned Clinic')
                                        ->relationship('clinic', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a clinic')
                                        ->native(false)
                                        ->visible(function ($get) {
                                            $selectedRoles = Role::whereIn('id', $get('roles') ?? [])->pluck('name')->toArray();
                                            return in_array('clinic_manager', $selectedRoles) || in_array('user', $selectedRoles);
                                        })
                                        ->required(function ($get) {
                                            $selectedRoles = Role::whereIn('id', $get('roles') ?? [])->pluck('name')->toArray();
                                            return in_array('clinic_manager', $selectedRoles) || in_array('user', $selectedRoles);
                                        })
                                ]),
                                Select::make('permissions')
                                    ->relationship('permissions', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),

                            ]),
                    ])
                    ->columnSpan(['lg' => fn (?User $record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('User created date')
                            ->state(fn (User $record): ?string => $record->created_at?->diffForHumans()),

                        TextEntry::make('updated_at')
                            ->label('Last modified at')
                            ->state(fn (User $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?User $record) => $record === null),
            ])
            ->columns(3);
    }

    // public static function configure(Schema $schema): Schema
    // {
    //     return $schema
    //         ->components([
    //             Wizard::make([
    //                 Step::make('Personal Information')
    //                     ->schema([
    //                         Section::make('Personal Information')
    //                             ->schema(static::getPersonalInformationComponents())
    //                             ->collapsible(),
    //                         Section::make('Contact Details')
    //                             ->schema(static::getContactDetailsComponents())
    //                             ->collapsible(),
    //                     ]),
    //                 Step::make('Account Settings')
    //                     ->schema([
    //                         Section::make('Account Settings')
    //                             ->schema([
    //                                 Grid::make(3)->schema([
    //                                     Select::make('how_did_you_hear')
    //                                         ->options([
    //                                             'Skin Genius' => 'Skin genius',
    //                                             'Social Media' => 'Social media',
    //                                             'Friend Referral' => 'Friend referral',
    //                                             'Google Search' => 'Google search',
    //                                             'Practo/Lybrate' => 'Practo/lybrate',
    //                                             'By Doctor' => 'By doctor',
    //                                             'Other' => 'Other',
    //                                         ]),
    //                                     ToggleButtons::make('opt_for_loyalty')
    //                                         ->inline()
    //                                         ->label('Opted for Loyalty Program?')
    //                                         ->default(false)
    //                                         ->boolean(),
    //                                     ToggleButtons::make('status')
    //                                         ->inline()
    //                                         ->options(UserStatus::class)
    //                                         ->default(UserStatus::Active)
    //                                         ->required(),
    //                                 ]),

    //                                 Grid::make(2)->schema([
    //                                     TextInput::make('password')
    //                                         ->password()
    //                                         ->required()
    //                                         ->placeholder('Password')
    //                                         ->revealable(),
    //                                 ]),

    //                             ])
    //                             ->collapsible(),
    //                     ]),
    //             ])
    //             ->columnSpanFull()
    //             ->previousAction(
    //                 fn (Action $action) => $action
    //                     ->label('Back')
    //                     ->color('secondary')
    //             )
    //             ->nextAction(
    //                 fn (Action $action) => $action
    //                     ->label('Next')
    //                     ->color('primary')
    //             )
    //             ->submitAction(
    //                 Action::make('submit')
    //                     ->label('Submit')
    //                     ->color('success')
    //                     ->submit('save')
    //             )
    //         ]);
    // }
}
