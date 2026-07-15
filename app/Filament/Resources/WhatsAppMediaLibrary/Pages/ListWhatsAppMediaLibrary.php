<?php

namespace App\Filament\Resources\WhatsAppMediaLibrary\Pages;

use App\Filament\Resources\WhatsAppMediaLibrary\WhatsAppMediaLibraryResource;
use App\Models\WhatsAppMediaLibrary;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppMediaLibrary extends ListRecords
{
    protected static string $resource = WhatsAppMediaLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromMeta')
                ->label('Sync from Meta')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Sync Media from Meta')
                ->modalDescription('This will refresh the URLs and download files for any media records currently stored in your database that have a Meta ID.')
                ->modalSubmitActionLabel('Sync Now')
                ->action(function () {
                    try {
                        $whatsAppService = app(WhatsAppService::class);
                        $mediaItems = WhatsAppMediaLibrary::whereNotNull('meta_media_id')->get();
                        
                        $count = 0;
                        foreach ($mediaItems as $media) {
                            $url = $whatsAppService->getMediaUrl($media->meta_media_id);
                            if ($url) {
                                $media->update(['meta_media_url' => $url]);
                                
                                // Download file if local file is missing
                                if (!empty($media->file_path) && !\Illuminate\Support\Facades\Storage::disk('public')->exists($media->file_path)) {
                                    $content = $whatsAppService->downloadMedia($url);
                                    if ($content) {
                                        \Illuminate\Support\Facades\Storage::disk('public')->put($media->file_path, $content);
                                    }
                                }
                                $count++;
                            }
                        }
                        
                        Notification::make()
                            ->title('Media Synced ✅')
                            ->body("{$count} media records synced with Meta.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            CreateAction::make()
                ->label('Upload Media')
                ->icon('heroicon-o-cloud-arrow-up'),
        ];
    }
}
