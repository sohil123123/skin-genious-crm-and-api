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
        $data['variable_type'] = $data['variable_type'] ?? $record->variable_type ?? 'number';

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
                $errorString = $result['error'] ?? '';
                $isStatusError = str_contains(strtolower($errorString), 'status') 
                    || str_contains(strtolower($errorString), 'can\'t be changed')
                    || str_contains(strtolower($errorString), 'only delete or add')
                    || str_contains(strtolower($errorString), '2388039');

                if ($isStatusError) {
                    // Fallback Workaround: Delete and Re-create template on Meta since status is locked for direct edits
                    $whatsAppService->deleteTemplate($record->name);
                    
                    $createResult = $whatsAppService->createTemplate([
                        'name' => $record->name,
                        'category' => $data['category'] ?? $record->category,
                        'language' => $data['language'] ?? $record->language,
                        'components' => $components,
                        'variable_type' => $data['variable_type'] ?? $record->variable_type,
                    ]);

                    if (!$createResult['success']) {
                        $errorDetails = $createResult['details'] ?? [];
                        $subcode = $errorDetails['error']['error_subcode'] ?? null;
                        $errorMsg = $createResult['error'] ?? '';

                        $isDeletionLock = ($subcode == 2388023)
                            || str_contains(strtolower($errorMsg), 'being deleted')
                            || str_contains(strtolower($errorMsg), 'try again in 4 weeks');

                        if ($isDeletionLock) {
                            // Generate a new versioned template name and retry
                            $newTemplateName = $this->incrementTemplateName($record->name);

                            $createResult = $whatsAppService->createTemplate([
                                'name' => $newTemplateName,
                                'category' => $data['category'] ?? $record->category,
                                'language' => $data['language'] ?? $record->language,
                                'components' => $components,
                                'variable_type' => $data['variable_type'] ?? $record->variable_type,
                            ]);

                            if ($createResult['success']) {
                                $data['name'] = $newTemplateName; // Save new versioned name to our DB
                            }
                        }
                    }

                    if ($createResult['success']) {
                        $data['meta_template_id'] = $createResult['template_id'];
                        $data['status'] = $createResult['status'] ?? 'PENDING';
                        
                        $recreatedName = $data['name'] ?? $record->name;

                        Notification::make()
                            ->title('Template Recreated on Meta 🔄')
                            ->body("Since Meta locked edits, it was recreated as '{$recreatedName}' for review.")
                            ->success()
                            ->duration(10000)
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Meta API Error ❌')
                            ->body('Failed to recreate template on Meta: ' . ($createResult['error'] ?? 'Unknown error'))
                            ->danger()
                            ->persistent()
                            ->send();

                        $this->halt();
                    }
                } else {
                    Notification::make()
                        ->title('Meta API Error ❌')
                        ->body($result['error'] ?? 'Failed to update template on Meta.')
                        ->danger()
                        ->persistent()
                        ->send();

                    $this->halt();
                }
            }
        }

        $data['last_synced_at'] = now();

        $record->update($data);

        return $record;
    }

    /**
     * Increment the version suffix of a template name (e.g. name -> name_v2 -> name_v3).
     */
    private function incrementTemplateName(string $name): string
    {
        if (preg_match('/_v(\d+)$/', $name, $matches)) {
            $version = (int)$matches[1] + 1;
            return preg_replace('/_v\d+$/', '_v' . $version, $name);
        }
        return $name . '_v2';
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
