<?php

namespace App\Filament\Resources\WhatsAppScheduledMessages\Pages;

use App\Filament\Resources\WhatsAppScheduledMessages\WhatsAppScheduledMessageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsAppScheduledMessage extends CreateRecord
{
    protected static string $resource = WhatsAppScheduledMessageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = 'pending';
        return $data;
    }
}
