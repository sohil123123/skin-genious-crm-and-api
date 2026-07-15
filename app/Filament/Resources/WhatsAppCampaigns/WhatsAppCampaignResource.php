<?php

namespace App\Filament\Resources\WhatsAppCampaigns;

use App\Filament\Resources\WhatsAppCampaigns\Pages;
use App\Filament\Resources\WhatsAppCampaigns\Schemas\WhatsAppCampaignForm;
use App\Filament\Resources\WhatsAppCampaigns\Tables\WhatsAppCampaignsTable;
use App\Models\WhatsAppCampaign;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsAppCampaignResource extends Resource
{
    protected static ?string $model = WhatsAppCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?int $navigationSort = 24;

    public static function form(Schema $schema): Schema
    {
        return WhatsAppCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsAppCampaignsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatsAppCampaigns::route('/'),
            'create' => Pages\CreateWhatsAppCampaign::route('/create'),
            'edit' => Pages\EditWhatsAppCampaign::route('/{record}/edit'),
            'view' => Pages\ViewWhatsAppCampaign::route('/{record}'),
        ];
    }
}
