<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\PhoneStatus;
use App\Models\Lead;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('full_name')->label('Name')->weight('bold')->placeholder('—'),

                    TextEntry::make('phone')
                        ->label('Phone')
                        ->copyable()
                        ->badge()
                        ->color(fn (Lead $record): string => $record->phone_status->getColor())
                        ->helperText(fn (Lead $record): ?string => $record->phone_status === PhoneStatus::NeedsReview
                            ? 'Repaired during import from "' . $record->phone_raw . '" — confirm before calling.'
                            : null),

                    TextEntry::make('email')->label('Email')->copyable()->placeholder('Not collected'),
                    TextEntry::make('city')->placeholder('—'),
                    TextEntry::make('state')->placeholder('—'),
                    TextEntry::make('pincode')->label('Pincode')->placeholder('—'),
                ]),

            Section::make('Pipeline')
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    TextEntry::make('status')->badge(),
                    TextEntry::make('source')->badge(),
                    TextEntry::make('assignedStaff.name')->label('Assigned to')->placeholder('Unassigned'),

                    TextEntry::make('matchedUser.name')
                        ->label('Existing patient')
                        ->badge()
                        ->color('warning')
                        ->icon('heroicon-o-identification')
                        ->placeholder('No match')
                        ->helperText(fn (Lead $record): ?string => $record->matched_user_id
                            ? 'This phone or email already belongs to a patient record. The lead was imported anyway and not merged.'
                            : null),

                    TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Answers from the lead form')
                ->description('Questions vary by form, so these are stored dynamically rather than as fixed fields.')
                ->schema([
                    View::make('filament.lead.custom-answers')
                        ->viewData(fn (Lead $record): array => ['record' => $record]),
                ]),

            Section::make('Meta attribution')
                ->description('Where on Facebook or Instagram this lead came from.')
                ->collapsible()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    // Names depend on the access token carrying ads
                    // permissions, so each falls back to its id rather than
                    // showing nothing — the question "which campaign was this?"
                    // stays answerable either way.
                    TextEntry::make('campaign_name')
                        ->label('Campaign')
                        ->placeholder('—')
                        ->default(fn (Lead $record): ?string => $record->campaign_id
                            ? 'ID ' . $record->campaign_id
                            : null),

                    TextEntry::make('adset_name')
                        ->label('Ad set')
                        ->placeholder('—')
                        ->default(fn (Lead $record): ?string => $record->adset_id
                            ? 'ID ' . $record->adset_id
                            : null),

                    TextEntry::make('ad_name')
                        ->label('Ad')
                        ->placeholder('—')
                        ->default(fn (Lead $record): ?string => $record->ad_id
                            ? 'ID ' . $record->ad_id
                            : null),

                    TextEntry::make('form_name')
                        ->label('Form')
                        ->placeholder('—')
                        ->default(fn (Lead $record): ?string => $record->form_id
                            ? 'ID ' . $record->form_id
                            : null),

                    TextEntry::make('page_name')->label('Page')->placeholder('—'),
                    TextEntry::make('platform')
                        ->label('Platform')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'ig' => 'Instagram',
                            'fb' => 'Facebook',
                            default => (string) ($state ?: '—'),
                        }),
                    // boolean() is an IconEntry capability in Filament v4;
                    // TextEntry has no equivalent.
                    IconEntry::make('is_organic')->label('Organic')->boolean(),
                    TextEntry::make('fb_lead_id')->label('Facebook lead ID')->copyable()->placeholder('—'),
                    TextEntry::make('fb_created_time')
                        ->label('Submitted at')
                        ->dateTime(config('leads.display.datetime_format'))
                        ->timezone(config('leads.display.timezone'))
                        ->placeholder('—'),
                    // A lead with no import batch used to mean "typed in by
                    // hand". Since Meta leads can now arrive over the webhook,
                    // that is no longer true and the distinction has to be
                    // drawn from whether Meta gave it a lead id.
                    TextEntry::make('arrived_via')
                        ->label('Arrived via')
                        ->state(fn (Lead $record): string => match (true) {
                            $record->lead_import_id !== null => (string) ($record->import?->original_filename ?: 'CSV import'),
                            $record->fb_lead_id !== null => 'Meta webhook (real time)',
                            default => 'Created manually',
                        })
                        ->url(fn (Lead $record): ?string => $record->lead_import_id
                            ? \App\Filament\Resources\LeadImports\LeadImportResource::getUrl('view', ['record' => $record->lead_import_id])
                            : null),
                ]),
        ]);
    }
}
