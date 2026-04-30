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

use Closure;

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
                TextInput::make('mobile')->required()->tel()->unique(ignoreRecord: true)->placeholder('Mobile Number'),
                TextInput::make('email')->label('Email address')->email()->unique(ignoreRecord: true)->placeholder('Email Address'),
                TextInput::make('occupation')->placeholder('Occupation'),
            ]),
            Grid::make(2)->schema([
                TextInput::make('city')->placeholder('City'),
                TextInput::make('pincode')->placeholder('Pincode'),
                // TextInput::make('referral_code')
                //     ->default(fn (string $context) => $context === 'create'
                //         ? 'REF' . random_int(100000, 999999)
                //         : context()->record?->referral_code
                //     )
                //     ->disabled() // user cannot change
                //     ->dehydrated() // still save value
                //     ->required(fn (string $context) => $context === 'create') // required only on create
                //     ->maxLength(32)
                //     ->unique(User::class, 'referral_code', ignoreRecord: true),
            ]),
            Grid::make(2)->schema([
                TextInput::make('address_line_1')->placeholder('Address Line 1'),
                TextInput::make('address_line_2')->placeholder('Address Line 2'),
            ])


        ];
    }

    public static function getMedicalBackgroundComponents(): array
    {
        return [
            Grid::make(3)->schema([
                Toggle::make('has_diabetes')
                    ->label('Has Diabetes')
                    ->default(false),
                Toggle::make('has_high_bp')
                    ->label('Has High Blood Pressure')
                    ->default(false),
                Toggle::make('has_cholesterol')
                    ->label('Has High Cholesterol')
                    ->default(false),
            ]),
            Grid::make(3)->schema([
                Toggle::make('has_asthma')
                    ->label('Has Asthma')
                    ->default(false),
                Toggle::make('has_heart_disease')
                    ->label('Has Heart Disease')
                    ->default(false),
                Toggle::make('has_anaemia')
                    ->label('Has Anaemia')
                    ->default(false),
            ]),
            Grid::make(3)->schema([
                Toggle::make('has_pcos')
                    ->label('Has PCOS')
                    ->default(false),
                Toggle::make('has_thyroid')
                    ->label('Has Thyroid Condition')
                    ->default(false),
            ]),
            Textarea::make('other_diseases')
                ->label('Other Diseases')
                ->placeholder('Describe any other medical conditions')
                ->nullable(),
            Textarea::make('current_medications')
                ->label('Current Medications')
                ->placeholder('List any current medications')
                ->nullable(),
            Textarea::make('allergies')
                ->label('Allergies')
                ->placeholder('List any known allergies')
                ->nullable(),
        ];
    }

    public static function getSkinProfileComponents(): array
    {
        return [
            Grid::make(8)->schema([
                // Select::make('skin_type')
                //     ->label('Skin Type')
                //     ->options([
                //         'Normal' => 'Normal',
                //         'Dry' => 'Dry',
                //         'Oily' => 'Oily',
                //         'Combination' => 'Combination',
                //         'Sensitive' => 'Sensitive',
                //     ])
                //     ->nullable(),
                Select::make('skin_quality')
                    ->label('Skin Quality')
                    ->options([
                        'Poor' => 'Poor',
                        'Fair' => 'Fair',
                        'Good' => 'Good',
                        'Excellent' => 'Excellent',
                    ])
                    ->nullable()
                    ->columnSpan(2),

                Select::make('skin_improvement')
                    ->label('Skin Improvement Goal')
                    ->options([
                        'Hydration' => 'Hydration',
                        'Smoothness' => 'Smoothness',
                        'Elasticity' => 'Elasticity',
                    ])
                    ->nullable()
                    ->columnSpan(2),

                Textarea::make('facials_history')
                    ->label('Facials History')
                    ->placeholder('History of previous facials and treatments')
                    ->nullable()
                    ->columnSpan(4),
            ]),

        ];
    }

    public static function getAestheticGoalsComponents(): array
    {
        return [
            Grid::make(3)->schema([
                Toggle::make('goal_less_tired')
                    ->label('Look Less Tired')
                    ->default(false),
                Toggle::make('goal_less_angry')
                    ->label('Look Less Angry')
                    ->default(false),
                Toggle::make('goal_less_sad')
                    ->label('Look Less Sad')
                    ->default(false),
            ]),
            Grid::make(3)->schema([
                Toggle::make('goal_less_saggy')
                    ->label('Look Less Saggy')
                    ->default(false),
                Toggle::make('goal_youthful')
                    ->label('Look More Youthful')
                    ->default(false),
                Toggle::make('goal_attractive')
                    ->label('Look More Attractive')
                    ->default(false),
            ]),
            Grid::make(3)->schema([
                Toggle::make('goal_soft_features')
                    ->label('Have Softer Features')
                    ->default(false),
                Toggle::make('goal_slim_face')
                    ->label('Have Slimmer Face')
                    ->default(false),
            ]),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Roles & Permissions')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('role_id')
                                        ->label('Role')
                                        ->options(Role::pluck('name', 'id'))
                                        ->preload()
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($set, ?int $state) {
                                            if ($state) {
                                                $selectedRole = Role::find($state);
                                                if ($selectedRole?->name === 'super_admin') {
                                                    $set('clinic_id', null);
                                                } else {
                                                    $set('clinic_id', auth()->user()->clinic_id);
                                                }
                                            } else {
                                                $set('clinic_id', null);
                                            }
                                        }),

                                    Select::make('clinic_id')
                                        ->label('Assigned Clinic')
                                        ->relationship(
                                            name: 'clinic',
                                            titleAttribute: 'name',
                                            modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => auth()->user()->clinic_id ? $query->where('id', auth()->user()->clinic_id) : $query
                                        )
                                        // ->searchable()
                                        // ->preload()
                                        ->placeholder('Select a clinic')
                                        ->default(fn () => auth()->user()->clinic_id)
                                        ->native(false)
                                        ->reactive()
                                        ->visible(fn ($get) => has_clinic_related_role($get('role_id')))
                                        ->required(fn ($get) => has_clinic_related_role($get('role_id')))
                                        ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                                            $livewire->validateOnly('clinic_id');
                                        })
                                        ->rules([
                                            fn ($get, ?User $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                                if (!$value)
                                                    return;

                                                $selectedRoleId = $get('role_id');
                                                if (!$selectedRoleId)
                                                    return;

                                                $selectedRole = Role::find($selectedRoleId);

                                                // Validate only if role is clinic_manager
                                                if (!$selectedRole || $selectedRole->name !== 'clinic_manager')
                                                    return;

                                                // Check if ANY user at this clinic has clinic_manager role
                                                $exists = User::where('clinic_id', $value)
                                                    ->whereHas('roles', fn ($q) => $q->where('name', 'clinic_manager'))
                                                    ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                                                    ->exists();

                                                if ($exists) {
                                                    $fail('This clinic already has a Clinic Manager assigned.');
                                                }
                                            }
                                        ])

                                ]),
                                Select::make('permissions')
                                    ->relationship('permissions', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->hidden(fn ($record) => ($record === null || $record->hasRole('client'))),

                            ]),

                        Section::make('Personal Information')
                            ->icon('heroicon-o-user-circle')
                            ->schema(static::getPersonalInformationComponents())
                            ->collapsible(),

                        Section::make('Contact Details')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema(static::getContactDetailsComponents())
                            ->collapsible(),

                        Section::make('Medical Background')
                            ->icon('heroicon-o-heart')
                            ->schema(static::getMedicalBackgroundComponents())
                            ->visible(fn ($get) => has_user_related_role($get('role_id')))
                            ->collapsible(),

                        Section::make('Skin Profile')
                            ->icon('heroicon-o-face-smile')
                            ->schema(static::getSkinProfileComponents())
                            ->visible(fn ($get) => has_user_related_role($get('role_id')))
                            ->collapsible(),

                        Section::make('Aesthetic Goals')
                            ->icon('heroicon-o-sparkles')
                            ->schema(static::getAestheticGoalsComponents())
                            ->visible(fn ($get) => has_user_related_role($get('role_id')))
                            ->collapsible(),

                        Section::make('Account Settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('how_did_you_hear')
                                        ->visible(fn ($get) => has_user_related_role($get('role_id')))
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
                                        ->visible(fn ($get) => has_user_related_role($get('role_id')))
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


                    ])
                    ->columnSpan(['lg' => fn (?User $record) => $record === null ? 3 : 2]),

                Section::make()
                    ->icon('heroicon-o-clock')
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
