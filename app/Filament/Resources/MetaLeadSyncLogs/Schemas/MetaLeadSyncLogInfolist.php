<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaLeadSyncLogs\Schemas;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\MetaLeadSyncLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class MetaLeadSyncLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Sync Overview')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])->schema([
                            TextEntry::make('created_at')
                                ->label('Received')
                                ->dateTime(config('leads.display.datetime_format'))
                                ->timezone(config('leads.display.timezone')),

                            TextEntry::make('status')
                                ->badge(),

                            TextEntry::make('attempts')
                                ->label('Tries')
                                ->badge()
                                ->color('gray'),

                            TextEntry::make('processed_at')
                                ->label('Processed At')
                                ->dateTime(config('leads.display.datetime_format'))
                                ->timezone(config('leads.display.timezone'))
                                ->placeholder('Not completed yet'),
                        ]),
                    ]),

                Section::make('Meta & Lead Information')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])->schema([
                            TextEntry::make('leadgen_id')
                                ->label('Meta Lead ID')
                                ->fontFamily('mono')
                                ->copyable()
                                ->weight('bold'),

                            TextEntry::make('metaPage.page_name')
                                ->label('Page')
                                ->placeholder('Unknown Page'),

                            TextEntry::make('metaPage.clinic.name')
                                ->label('Clinic')
                                ->badge()
                                ->color('info')
                                ->placeholder('N/A'),

                            TextEntry::make('lead_id')
                                ->label('CRM Lead')
                                ->placeholder('Not created')
                                ->formatStateUsing(fn ($state): string => '#' . $state)
                                ->url(fn (MetaLeadSyncLog $record): ?string => $record->lead_id
                                    ? LeadResource::getUrl('view', ['record' => $record->lead_id])
                                    : null)
                                ->color('primary'),
                        ]),
                    ]),

                Section::make('Execution Detail & Error')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (MetaLeadSyncLog $record): bool => !empty($record->error_message))
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Detail')
                            ->placeholder('No error logged')
                            ->columnSpanFull(),
                    ]),

                Section::make('Payload')
                    ->icon('heroicon-o-code-bracket')
                    ->schema([
                        View::make('filament.payload-preview')
                            ->viewData(fn (MetaLeadSyncLog $record): array => [
                                'payload' => $record->payload,
                                'record' => $record,
                            ]),
                    ]),
            ])
            ->columns(1);
    }
}
