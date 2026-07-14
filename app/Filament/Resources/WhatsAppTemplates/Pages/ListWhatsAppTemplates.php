<?php

namespace App\Filament\Resources\WhatsAppTemplates\Pages;

use App\Filament\Resources\WhatsAppTemplates\WhatsAppTemplateResource;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppTemplates extends ListRecords
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromMeta')
                ->label('Sync from Meta')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Sync Templates from Meta')
                ->modalDescription('This will fetch all templates from your WhatsApp Business Account and sync them to the local database. Existing templates will be updated.')
                ->modalSubmitActionLabel('Sync Now')
                ->action(function () {
                    $whatsAppService = app(WhatsAppService::class);
                    $count = $whatsAppService->syncTemplatesFromMeta();

                    if ($count > 0) {
                        Notification::make()
                            ->title('Sync Completed ✅')
                            ->body("{$count} templates synced from Meta successfully.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Sync Result')
                            ->body('No templates found to sync. Please check your WhatsApp API credentials.')
                            ->warning()
                            ->send();
                    }
                }),

            CreateAction::make()
                ->label('Create Template')
                ->icon('heroicon-o-plus'),
        ];
    }
}
