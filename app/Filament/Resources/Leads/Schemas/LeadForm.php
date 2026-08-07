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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label('Phone')
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
                        ->email()
                        ->maxLength(255),

                    TextInput::make('city')->maxLength(255),
                    TextInput::make('state')->maxLength(255),
                    TextInput::make('pincode')->label('Pincode')->maxLength(20),
                ]),

            Section::make('Pipeline')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    Select::make('status')
                        ->options(LeadStatus::options())
                        ->default(LeadStatus::New->value)
                        ->required()
                        ->native(false),

                    Select::make('source')
                        ->options(LeadSource::options())
                        ->default(LeadSource::Manual->value)
                        ->required()
                        ->native(false),

                    Select::make('assigned_to')
                        ->label('Assigned to')
                        ->options(fn (): array => User::query()
                            ->withoutGlobalScopes()
                            ->when(
                                ! check_role(config('project.roles.super_admin')),
                                fn (Builder $query) => $query->where('clinic_id', auth()->user()?->clinic_id)
                            )
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->name])
                            ->all())
                        ->searchable()
                        ->placeholder('Unassigned'),

                    Select::make('clinic_id')
                        ->label('Clinic')
                        ->relationship('clinic', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->default(fn (): ?int => auth()->user()?->clinic_id)
                        ->visible(fn (): bool => check_role(config('project.roles.super_admin')))
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->label('Notes')
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
                    TextInput::make('campaign_name')->label('Campaign')->maxLength(255),
                    TextInput::make('adset_name')->label('Ad set')->maxLength(255),
                    TextInput::make('ad_name')->label('Ad')->maxLength(255),
                    TextInput::make('form_name')->label('Form')->maxLength(255),
                    TextInput::make('platform')->label('Platform')->maxLength(20),
                    TextInput::make('fb_lead_id')
                        ->label('Facebook lead ID')
                        ->maxLength(64)
                        ->helperText('Used to recognise this lead if the same export is imported again.'),
                ]),
        ]);
    }
}
