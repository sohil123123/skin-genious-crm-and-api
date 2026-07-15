<?php

namespace App\Filament\Resources\WhatsAppMediaLibrary\Pages;

use App\Filament\Resources\WhatsAppMediaLibrary\WhatsAppMediaLibraryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateWhatsAppMediaLibrary extends CreateRecord
{
    protected static string $resource = WhatsAppMediaLibraryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = auth()->id();

        if (!empty($data['file_path'])) {
            $filePath = $data['file_path'];
            $data['file_name'] = basename($filePath);
            $data['mime_type'] = Storage::disk('public')->mimeType($filePath);
            $data['file_size'] = Storage::disk('public')->size($filePath);
        }

        return $data;
    }
}
