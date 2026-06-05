<?php

namespace App\Filament\Resources\WhatsAppMessageLogs\Pages;

use App\Filament\Resources\WhatsAppMessageLogs\WhatsAppMessageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppMessageLogs extends ListRecords
{
    protected static string $resource = WhatsAppMessageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action for logs
        ];
    }
}
