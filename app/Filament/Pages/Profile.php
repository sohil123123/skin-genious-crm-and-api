<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;

use Filament\Support\Icons\Heroicon;

class Profile extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.profile';
    protected static ?string $title = 'My Profile';
    protected static bool $shouldRegisterNavigation = false;

    public ?User $user = null;

    public array $data = [];

    // 👇 integer index (1 = View, 2 = Edit)
    public int $activeTab = 1;

    public function mount(): void
    {
        $this->user = auth()->user();

        // Prefill auth user data
        $this->data = $this->user->toArray();
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('ProfileTabs')
                    ->activeTab(fn () => $this->activeTab)
                    ->tabs([
                        // 🔹 VIEW TAB
                        Tab::make('View Profile')
                            ->icon(Heroicon::Eye)
                            ->schema([
                                Section::make('Personal Information')
                                    ->schema([
                                        Grid::make(5)->schema([
                                            TextEntry::make('first_name')->placeholder('N/A'),
                                            TextEntry::make('last_name')->placeholder('N/A'),
                                            TextEntry::make('gender')->placeholder('N/A'),
                                            TextEntry::make('date_of_birth')->date()->placeholder('N/A'),
                                            TextEntry::make('clinic.name')->label('Assigned Clinic')->placeholder('N/A'),
                                        ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Contact Details')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('mobile')->placeholder('N/A'),
                                            TextEntry::make('email')->label('Email address')->placeholder('N/A'),
                                            TextEntry::make('occupation')->placeholder('N/A'),
                                        ]),
                                        Grid::make(3)->schema([
                                            TextEntry::make('city')->placeholder('N/A'),
                                            TextEntry::make('pincode')->placeholder('N/A'),
                                            TextEntry::make('referral_code')->placeholder('N/A'),
                                        ]),
                                        Grid::make(2)->schema([
                                            TextEntry::make('address_line_1')->placeholder('N/A'),
                                            TextEntry::make('address_line_2')->placeholder('N/A'),
                                        ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Medical Background')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            IconEntry::make('has_diabetes')
                                                ->label('Has Diabetes')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('has_high_bp')
                                                ->label('Has High Blood Pressure')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('has_cholesterol')
                                                ->label('Has High Cholesterol')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('has_asthma')
                                                ->label('Has Asthma')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                        ]),
                                        Grid::make(4)->schema([

                                            IconEntry::make('has_heart_disease')
                                                ->label('Has Heart Disease')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('has_anaemia')
                                                ->label('Has Anaemia')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('has_pcos')
                                                ->label('Has PCOS')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('has_thyroid')
                                                ->label('Has Thyroid Condition')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                        ]),
                                        Grid::make(3)->schema([
                                            TextEntry::make('other_diseases')
                                                ->label('Other Diseases')
                                                ->placeholder('No other diseases listed'),
                                            TextEntry::make('current_medications')
                                                ->label('Current Medications')
                                                ->placeholder('No current medications listed'),
                                            TextEntry::make('allergies')
                                                ->label('Allergies')
                                                ->placeholder('No known allergies listed'),
                                        ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Skin Profile')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            TextEntry::make('skin_type')->label('Skin Type')->placeholder('N/A'),
                                            TextEntry::make('skin_quality')->label('Skin Quality')->placeholder('N/A'),
                                            TextEntry::make('skin_improvement')->label('Skin Improvement Goal')->placeholder('N/A'),
                                            TextEntry::make('facials_history')->label('Facials History')->placeholder('No facials history provided'),
                                        ]),

                                    ])
                                    ->collapsible(),

                                Section::make('Aesthetic Goals')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            IconEntry::make('goal_less_tired')
                                                ->label('Look Less Tired')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('goal_less_angry')
                                                ->label('Look Less Angry')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('goal_less_sad')
                                                ->label('Look Less Sad')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('goal_less_saggy')
                                                ->label('Look Less Saggy')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                        ]),
                                        Grid::make(4)->schema([
                                            IconEntry::make('goal_youthful')
                                                ->label('Look More Youthful')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('goal_attractive')
                                                ->label('Look More Attractive')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('goal_soft_features')
                                                ->label('Have Softer Features')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('goal_slim_face')
                                                ->label('Have Slimmer Face')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                        ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Account Settings')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('how_did_you_hear')->placeholder('N/A'),
                                            IconEntry::make('opt_for_loyalty')
                                                ->label('Opted for Loyalty Program?')
                                                ->boolean()
                                                ->placeholder('N/A'),
                                            IconEntry::make('is_active')->boolean()->placeholder('N/A'),
                                        ]),
                                    ])
                                    ->collapsible(),


                                Section::make('Record Information')
                                    ->description('Timestamps for creation, update, and deletion.')
                                    ->icon('heroicon-o-clock')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Created At')
                                            ->dateTime('Y-m-d H:i:s'),
                                        TextEntry::make('updated_at')
                                            ->label('Updated At')
                                            ->dateTime('Y-m-d H:i:s'),
                                        TextEntry::make('deleted_at')
                                            ->label('Deleted At')
                                            ->dateTime('Y-m-d H:i:s')
                                            ->placeholder('Not deleted'),
                                    ])
                                    ->columns(3)
                                    ->collapsible(),
                            ])
                            ->columns(1),

                        // 🔹 EDIT TAB
                        Tab::make('Edit Profile')
                            ->icon(Heroicon::Pencil)
                            ->schema([
                                Section::make('Personal Information')
                                    ->schema(static::getPersonalInformationComponents())
                                    ->collapsible(),

                                Section::make('Contact Details')
                                    ->schema(static::getContactDetailsComponents())
                                    ->collapsible(),

                                Section::make('Medical Background')
                                    ->schema(static::getMedicalBackgroundComponents())
                                    ->collapsible(),

                                Section::make('Skin Profile')
                                    ->schema(static::getSkinProfileComponents())
                                    ->collapsible(),

                                Section::make('Aesthetic Goals')
                                    ->schema(static::getAestheticGoalsComponents())
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

                                            TextInput::make('password')
                                                ->password()
                                                ->revealable()
                                                ->placeholder('Password')
                                                ->required(fn (string $context): bool => $context === 'create')
                                                ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                                                ->dehydrated(fn ($state) => filled($state))
                                        ]),


                                        Action::make('edit')
                                            ->label('Save Changes')
                                            ->action(function () {
                                                $this->submit();
                                            }),
                                    ])
                                    ->collapsible(),
                            ]),
                    ]),
            ])
            ->statePath('data')
            ->model($this->user);
    }

    public function submit(): void
    {
        $data = $this->data;

        $this->user->fill($data);

        if (!empty($data['password'])) {
            $this->user->password = bcrypt($data['password']);
        }

        $this->user->save();

        $this->activeTab = 0; // switch back to View

        Notification::make()
            ->title('Profile updated successfully!')
            ->success()
            ->body('Your updated profile details have been saved.')
            ->send();
    }

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
            Grid::make(3)->schema([
                Select::make('skin_type')
                    ->label('Skin Type')
                    ->options([
                        'Normal' => 'Normal',
                        'Dry' => 'Dry',
                        'Oily' => 'Oily',
                        'Combination' => 'Combination',
                        'Sensitive' => 'Sensitive',
                    ])
                    ->nullable(),
                Select::make('skin_quality')
                    ->label('Skin Quality')
                    ->options([
                        'Poor' => 'Poor',
                        'Fair' => 'Fair',
                        'Good' => 'Good',
                        'Excellent' => 'Excellent',
                    ])
                    ->nullable(),
                Select::make('skin_improvement')
                    ->label('Skin Improvement Goal')
                    ->options([
                        'Hydration' => 'Hydration',
                        'Smoothness' => 'Smoothness',
                        'Elasticity' => 'Elasticity',
                    ])
                    ->nullable(),
            ]),
            Textarea::make('facials_history')
                ->label('Facials History')
                ->placeholder('History of previous facials and treatments')
                ->nullable(),
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
}
