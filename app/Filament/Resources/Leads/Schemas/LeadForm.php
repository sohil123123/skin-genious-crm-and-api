<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\PhoneStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\Lead\PhoneNormalizerService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('full_name')
                        ->label('Full name')
                        ->placeholder('Enter full name')
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label('Phone')
                        ->placeholder('Enter phone number')
                        ->required()
                        ->maxLength(20)
                        ->tel()
                        // Editing is the intended fix for a salvaged number, so
                        // the original is shown alongside the field rather than
                        // being buried on the detail screen.
                        ->helperText(fn (?Lead $record): ?string => $record?->phone_status === PhoneStatus::NeedsReview
                            ? 'Repaired during import from: ' . $record->phone_raw
                            : null)
                        ->hintColor('warning')
                        ->hint(fn (?Lead $record): ?string => $record?->phone_status === PhoneStatus::NeedsReview ? 'Needs review' : null)
                        // Re-normalising on save clears the review flag once a
                        // human has confirmed or corrected the number.
                        ->dehydrateStateUsing(function (?string $state): ?string {
                            $result = app(PhoneNormalizerService::class)->normalize($state);

                            return $result->value ?? $state;
                        })
                        ->afterStateUpdated(fn () => null),

                    TextInput::make('email')
                        ->label('Email')
                        ->placeholder('Enter email address')
                        ->email()
                        ->maxLength(255),

                    TextInput::make('city')
                        ->label('City')
                        ->placeholder('Enter city')
                        ->maxLength(255),

                    TextInput::make('state')
                        ->label('State')
                        ->placeholder('Enter state')
                        ->maxLength(255),

                    TextInput::make('pincode')
                        ->label('Pincode')
                        ->placeholder('Enter pincode')
                        ->maxLength(20),
                ]),

            Section::make('Pipeline')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('clinic_id')
                        ->label('Clinic')
                        ->relationship('clinic', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->placeholder('Select clinic')
                        ->default(fn (): ?int => auth()->user()?->clinic_id)
                        ->visible(fn (): bool => check_role(config('project.roles.super_admin')))
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('assigned_to', null))
                        ->columnSpanFull(),

                    Select::make('status')
                        ->options(LeadStatus::options())
                        ->default(LeadStatus::New->value)
                        ->required()
                        ->placeholder('Select status')
                        ->native(false),

                    Select::make('source')
                        ->options(LeadSource::options())
                        ->default(LeadSource::Manual->value)
                        ->required()
                        ->placeholder('Select source')
                        ->native(false),

                    Select::make('assigned_to')
                        ->label('Assigned to')
                        ->options(function (callable $get): array {
                            $clinicId = $get('clinic_id') ?: auth()->user()?->clinic_id;

                            if (! $clinicId) {
                                return [];
                            }

                            return User::query()
                                ->withoutGlobalScopes()
                                ->role(config('project.roles.clinic_head'))
                                ->where('clinic_id', $clinicId)
                                ->orderBy('first_name')
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->name])
                                ->all();
                        })
                        ->searchable()
                        ->placeholder('Select assigned staff'),

                    Textarea::make('notes')
                        ->label('Notes')
                        ->placeholder('Enter notes...')
                        ->rows(4)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ]),

            Section::make('Facebook attribution')
                ->description('Captured from the lead export. Editing these values will not change anything in Meta.')
                ->collapsible()
                ->collapsed()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextInput::make('campaign_name')
                        ->label('Campaign')
                        ->placeholder('Enter campaign name')
                        ->maxLength(255),

                    TextInput::make('adset_name')
                        ->label('Ad set')
                        ->placeholder('Enter ad set name')
                        ->maxLength(255),

                    TextInput::make('ad_name')
                        ->label('Ad')
                        ->placeholder('Enter ad name')
                        ->maxLength(255),

                    TextInput::make('form_name')
                        ->label('Form')
                        ->placeholder('Enter form name')
                        ->maxLength(255),

                    TextInput::make('platform')
                        ->label('Platform')
                        ->placeholder('Enter platform (e.g. Facebook, Instagram)')
                        ->maxLength(20),

                    TextInput::make('fb_lead_id')
                        ->label('Facebook lead ID')
                        ->placeholder('Enter Facebook lead ID')
                        ->maxLength(64)
                        ->helperText('Used to recognise this lead if the same export is imported again.'),
                ]),
        ]);
    }
}
