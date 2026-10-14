<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Filament\Widgets\SingleSessionClients;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;

use Filament\Notifications\Notification;
class CreateWhatsAppCampaign extends CreateRecord
{
    protected static string $resource = WhatsAppCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
        ];
    }

    /**
     * Pre-select the audience when arriving from a bulk "Create WhatsApp
     * Campaign" action (e.g. the Single Session list on the Best Action
     * Dashboard). Pulled from the session so it applies once only.
     */
    protected function afterFill(): void
    {
        $userIds = session()->pull(SingleSessionClients::CAMPAIGN_PREFILL_SESSION_KEY);

        if (empty($userIds)) {
            return;
        }

        $this->form->fill([
            ...$this->form->getRawState(),
            'audience_type' => 'specific',
            'audience_user_ids' => array_map('intval', $userIds),
        ]);

        Notification::make()
            ->title(count($userIds) . ' clients pre-selected')
            ->body('Pick a template and save to message them.')
            ->info()
            ->send();
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('WhatsApp Campaign created 🎉')
            ->body('The WhatsApp Campaign details have been successfully created.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = !empty($data['scheduled_at']) ? 'scheduled' : 'draft';
        return $data;
    }
}
