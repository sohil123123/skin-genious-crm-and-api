<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Services\WhatsAppCampaignService;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ViewWhatsAppCampaign extends ViewRecord
{
    protected static string $resource = WhatsAppCampaignResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Campaign Info')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('name')->weight('bold'),
                        TextEntry::make('template.name')->label('Template')->badge()->color('info'),
                        TextEntry::make('status')->badge(),
                    ]),
                    TextEntry::make('description')->columnSpanFull(),
                ]),

            Section::make('Analytics')
                ->schema([
                    Grid::make(5)->schema([
                        TextEntry::make('total_recipients')->label('Total'),
                        TextEntry::make('sent_count')->label('Sent')->color('success'),
                        TextEntry::make('delivered_count')->label('Delivered')->color('info'),
                        TextEntry::make('read_count')->label('Read')->color('primary'),
                        TextEntry::make('failed_count')->label('Failed')->color('danger'),
                    ]),
                ]),

            Section::make('Schedule')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('scheduled_at')->dateTime()->placeholder('Immediate'),
                        TextEntry::make('started_at')->dateTime()->placeholder('Not started'),
                        TextEntry::make('completed_at')->dateTime()->placeholder('Not completed'),
                    ]),
                ]),
        ]);
    }
}
