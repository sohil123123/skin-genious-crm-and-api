<?php

namespace App\Filament\Resources\WhatsAppTemplates\Pages;

use App\Filament\Resources\WhatsAppTemplates\WhatsAppTemplateResource;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

use Filament\Schemas\Components\Tabs\Tab;

class ListWhatsAppTemplates extends ListRecords
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Template')
                ->icon('heroicon-o-plus'),

            Action::make('templateLibrary')
                ->label('Template Library')
                ->icon('heroicon-o-rectangle-stack')
                ->color('info')
                ->url(fn(): string => WhatsAppTemplateResource::getUrl('library')),

            Action::make('syncFromMeta')
                ->label('Sync from Meta')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
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
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Templates')
                ->icon('heroicon-o-rectangle-stack')
                ->badge(WhatsAppTemplateResource::getEloquentQuery()->count())
                ->badgeColor('gray'),

            'utility' => Tab::make('Utility')
                ->icon('heroicon-o-wrench-screwdriver')
                ->query(fn($query) => $query->where('category', 'UTILITY'))
                ->badge(WhatsAppTemplateResource::getEloquentQuery()->where('category', 'UTILITY')->count())
                ->badgeColor('primary'),

            'authentication' => Tab::make('Authentication')
                ->icon('heroicon-o-shield-check')
                ->query(fn($query) => $query->where('category', 'AUTHENTICATION'))
                ->badge(WhatsAppTemplateResource::getEloquentQuery()->where('category', 'AUTHENTICATION')->count())
                ->badgeColor('warning'),

            'marketing' => Tab::make('Marketing')
                ->icon('heroicon-o-megaphone')
                ->query(fn($query) => $query->where('category', 'MARKETING'))
                ->badge(WhatsAppTemplateResource::getEloquentQuery()->where('category', 'MARKETING')->count())
                ->badgeColor('info'),
        ];
    }
}
