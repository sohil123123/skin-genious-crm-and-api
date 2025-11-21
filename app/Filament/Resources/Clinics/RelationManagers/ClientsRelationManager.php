<?php

namespace App\Filament\Resources\Clinics\RelationManagers;

use App\Filament\Resources\Clinics\ClinicResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\Action;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Illuminate\Contracts\View\View;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Spatie\Permission\Models\Permission;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Schema;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group as FromGroup;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Hidden;
use Illuminate\Database\Eloquent\Model;

use App\Models\User;
use App\Models\Role;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    // protected static ?string $relatedResource = ClinicResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FromGroup::make()
                    ->schema([
                        Hidden::make('role_id')->default(4), // client role id

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
                            ->collapsible(),

                        Section::make('Skin Profile')
                            ->icon('heroicon-o-face-smile')
                            ->schema(static::getSkinProfileComponents())
                            ->collapsible(),

                        Section::make('Aesthetic Goals')
                            ->icon('heroicon-o-sparkles')
                            ->schema(static::getAestheticGoalsComponents())
                            ->collapsible(),

                        Section::make('Account Settings')
                            ->icon('heroicon-o-cog-6-tooth')
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
                    ])
                    ->columnSpan(['lg' => 3]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                // CreateAction::make()->label('New Client')->icon('heroicon-o-plus')->mutateFormDataUsing(fn(array $data) => $this->mutateClientData($data)),
                CreateAction::make()
                    ->label('Add Client')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['clinic_id'] = $this->ownerRecord->id;

                        return $data;
                    })
                    ->using(function (array $data, string $model): Model {
                        // 🔹 Extract role_id from form data
                        $roleId = $data['role_id'] ?? null;

                        // 🔹 Don't try to save role_id into users table
                        unset($data['role_id']);

                        /** @var \App\Models\User $record */
                        $record = $model::create($data);

                        // 🔹 Sync roles AFTER user is created
                        if ($roleId) {
                            if ($role = Role::find($roleId)) {
                                $record->syncRoles([$role]);
                            }
                        }

                        return $record;
                    }),
            ])
            ->deferLoading()
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->searchable(['first_name', 'last_name'])
                    ->formatStateUsing(fn ($record) => trim($record->first_name . ' ' . ($record->last_name ?? ''))),
                TextColumn::make('mobile')->searchable(),
                TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match (strtolower($state)) {
                        'male'   => '👨 Male',
                        'female' => '👩 Female',
                        default  => '❓ Unknown',
                    })
                    ->color(fn ($state) => match (strtolower($state)) {
                        'male'   => 'info',
                        'female' => 'danger',
                        default  => 'gray',
                    })
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('email')->label('Email address')->searchable()->toggleable()->placeholder('-'),
                ToggleColumn::make('is_active')
                    ->label('Status')
                    ->onIcon('heroicon-o-bolt')
                    ->offIcon('heroicon-o-power')
                    ->offColor('dark-danger')
                    ->onColor('success')
                    ->sortable()
                    ->afterStateUpdated(function ($state, $record) {
                        if (! auth()->user()->can('toggle_user_status')) {
                            Notification::make()
                                ->title('Access Denied')
                                ->body('You do not have permission to update user status.')
                                ->danger()
                                ->send();

                            $record->is_active = ! $state;
                            $record->save();

                            return;
                        }

                        $record->is_active = $state;
                        $record->save();

                        Notification::make()
                            ->title('Status Updated')
                            ->body("User status has been updated successfully.")
                            ->success()
                            ->send();
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ])
                    ->searchable(),

                SelectFilter::make('is_active')
                    ->options([
                        1 => 'Active',
                        0 => 'Deactive',
                    ])
                    ->label('Status')
                    ->searchable(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(3)
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                Action::make('new_assessment')
                    ->label('New Assessment')
                    ->visible(fn ($record) => $record->hasRole('client'))
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->action(function ($record) {
                        $assessmentUrl = new_assessment($record);
                        return redirect($assessmentUrl);
                    })
                    ->requiresConfirmation(),

                Action::make('holiday')
                    ->visible(fn ($record) => $record->hasRole('therapist'))
                    ->icon('heroicon-o-rectangle-stack')
                    ->iconButton()
                    ->color('info')
                    ->tooltip('Manage Holidays')
                    ->url(fn ($record) => route('filament.admin.resources.users.holidays', ['record' => $record])),

                Action::make('appointment')
                    ->visible(fn ($record) => $record->hasRole('client'))
                    ->icon('heroicon-o-calendar-days')
                    ->iconButton()
                    ->color('info')
                    ->tooltip('Manage Appointments')
                    ->url(fn ($record) => route('filament.admin.resources.users.appointments', ['record' => $record])),

                Action::make('assessment')
                    ->visible(fn ($record) => $record->hasRole('client'))
                    ->icon('heroicon-o-clipboard-document')
                    ->iconButton()
                    ->color('info')
                    ->tooltip('Manage Assessments')
                    ->url(fn ($record) => route('filament.admin.resources.users.assessments', ['record' => $record])),

                ViewAction::make(),
                EditAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make()
                    ->successNotification(
                        Notification::make()
                            ->title('Client Restored 🎉')
                            ->body('The selected client have been restored successfully.')
                            ->success()
                    ),
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('Client Deleted 🎉')
                            ->body("The client **{$record->name}** has been removed successfully.")
                            ->success();
                    }),
                Action::make('permissions')
                    ->label('Permissions')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->slideOver()
                    ->form([
                        CheckboxList::make('permissions')
                            ->label('Manage Permissions')
                            ->options(Permission::all()->pluck('name', 'id'))
                            ->columns(3)
                            ->searchable()
                            ->bulkToggleable()
                            ->default(fn($record) => $record->permissions()->pluck('id')->toArray()),
                    ])
                    ->visible(fn () => auth()->user()?->can('toggle_user_permissions'))
                    ->action(function (array $data, $record) {
                        if (! auth()->user()->can('toggle_user_permissions')) {
                            Notification::make()
                                ->title('Access Denied')
                                ->body('You do not have permission to update user status.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        if ($record->hasRole('super_admin'))
                        {
                            Notification::make()
                                ->title('Super Admin Permissions Locked')
                                ->body('You cannot modify permissions for Super Admin.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $permissions = collect($data)
                            ->filter(fn ($value, $key) => str_starts_with($key, 'permissions_'))
                            ->flatten()
                            ->filter()
                            ->toArray();
                        
                        $record->syncPermissions($permissions ?? []);

                        Notification::make()
                            ->title('Permissions updated')
                            ->body("Permissions for role **{$record->name}** have been saved successfully.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->successNotification(
                            Notification::make()
                                ->title('Users Deleted 🎉')
                                ->body('The selected users have been deleted successfully.')
                                ->success()
                        ),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->groups([
                Group::make('gender')->label('Gender')->collapsible(),
                Group::make('created_at')->date(),
            ])
            ->emptyStateDescription('Once you create your first user, it will appear here.');
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
                TextInput::make('mobile')->required()->tel()->unique(ignoreRecord: true)->placeholder('Mobile Number'),
                TextInput::make('email')->label('Email address')->email()->unique(ignoreRecord: true)->placeholder('Email Address'),
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
}
