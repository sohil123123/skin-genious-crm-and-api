<?php

namespace App\Filament\Resources\WhatsAppScheduledMessages\Pages;

use App\Filament\Resources\WhatsAppScheduledMessages\WhatsAppScheduledMessageResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListWhatsAppScheduledMessages extends ListRecords
{
    protected static string $resource = WhatsAppScheduledMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Schedule Message')
                ->icon('heroicon-o-plus'),
        ];
    }
}
