<?php

namespace App\Filament\Resources\WhatsAppTemplates\Pages;

use App\Filament\Resources\WhatsAppTemplates\WhatsAppTemplateResource;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWhatsAppTemplate extends EditRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Back to List')->icon('heroicon-o-arrow-left')->url(static::getResource()::getUrl('index'))->color('gray'),
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->before(function (WhatsAppTemplate $record) {
                    // Delete from Meta Cloud API before local delete
                    $whatsAppService = app(WhatsAppService::class);
                    $result = $whatsAppService->deleteTemplate($record->name);

                    if (!$result['success']) {
                        Notification::make()
                            ->title('Meta API Warning ⚠️')
                            ->body('Could not delete from Meta: ' . ($result['error'] ?? 'Unknown error') . '. Removing locally only.')
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Mutate data before filling the form (transform stored data to form format).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Transform variable_samples from {key: value} map to [{key, value}] repeater format
        if (!empty($data['variable_samples']) && is_array($data['variable_samples'])) {
            $samples = [];
            foreach ($data['variable_samples'] as $key => $value) {
                $samples[] = [
                    'key' => (string) $key,
                    'value' => (string) $value,
                ];
            }
            $data['variable_samples'] = $samples;
        }

        return $data;
    }

    /**
     * Intercept record update to push changes to Meta Cloud API.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Transform variable_samples from repeater format to key-value
        $variableSamples = $this->transformVariableSamples($data['variable_samples'] ?? []);
        $data['variable_samples'] = $variableSamples;

        // Build components from form data
        $tempModel = new WhatsAppTemplate();
        $tempModel->fill($data);
        $components = $tempModel->buildComponentsForApi();
        $data['components'] = $components;

        // Push to Meta if we have a meta_template_id
        if ($record->meta_template_id) {
            $whatsAppService = app(WhatsAppService::class);

            $result = $whatsAppService->editTemplate($record->meta_template_id, [
                'components' => $components,
                'category' => $data['category'] ?? $record->category,
            ]);

            if (!$result['success']) {
                Notification::make()
                    ->title('Meta API Error ❌')
                    ->body($result['error'] ?? 'Failed to update template on Meta.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        $data['last_synced_at'] = now();

        $record->update($data);

        return $record;
    }

    /**
     * Customize the default success notification.
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Template Updated ✅')
            ->body('Template has been updated on Meta successfully.')
            ->success();
    }

    /**
     * Redirect to the list templates page after saving.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Transform repeater-format variable samples [{key, value}] to {key: value} map.
     */
    protected function transformVariableSamples(array $samples): array
    {
        $result = [];

        foreach ($samples as $sample) {
            if (isset($sample['key'], $sample['value'])) {
                $result[$sample['key']] = $sample['value'];
            }
        }

        return $result;
    }
}
