<?php

namespace App\Filament\Resources\WhatsAppTemplates\Pages;

use App\Filament\Resources\WhatsAppTemplates\WhatsAppTemplateResource;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions\Action;

class CreateWhatsAppTemplate extends CreateRecord
{
    protected static string $resource = WhatsAppTemplateResource::class;

    public function mount(): void
    {
        parent::mount();

        $category = request()->query('category');
        $presetKey = request()->query('preset');

        if ($category && $presetKey) {
            $library = \App\Services\WhatsAppTemplateLibraryService::getTemplates();
            $preset = $library[strtoupper($category)][$presetKey] ?? null;

            if ($preset) {
                $this->form->fill([
                    'name' => $preset['title'],
                    'category' => $preset['category'],
                    'header_type' => $preset['header_type'] ?? 'none',
                    'header_content' => $preset['header_content'] ?? null,
                    'body_text' => $preset['body_text'],
                    'footer_text' => $preset['footer_text'] ?? null,
                    'buttons' => $preset['buttons'] ?? [],
                    'variable_samples' => $preset['variable_samples'] ?? [],
                    'variable_type' => 'number',
                    'language' => 'en_US',
                ]);
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('templateLibrary')
                ->label('Browse Template Library')
                ->icon('heroicon-o-rectangle-stack')
                ->color('info')
                ->url(fn(): string => WhatsAppTemplateResource::getUrl('library')),

            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    /**
     * Intercept record creation to push the template to Meta Cloud API first.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $whatsAppService = app(WhatsAppService::class);

        // Build a temporary model to generate components
        $tempModel = new WhatsAppTemplate();
        $tempModel->fill($data);
        $components = $tempModel->buildComponentsForApi();

        // Transform variable_samples from repeater format to key-value
        $variableSamples = $this->transformVariableSamples($data['variable_samples'] ?? []);

        // Push to Meta Cloud API
        $result = $whatsAppService->createTemplate([
            'name' => $data['name'],
            'category' => $data['category'],
            'variable_type' => $data['variable_type'] ?? 'number',
            'language' => $data['language'],
            'components' => $components,
        ]);

        if (!$result['success']) {
            Notification::make()
                ->title('Meta API Error ❌')
                ->body($result['error'] ?? 'Failed to create template on Meta.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }

        // Success — update with Meta response
        $data['meta_template_id'] = $result['template_id'] ?? null;
        $data['status'] = $result['status'] ?? 'PENDING';
        $data['variable_type'] = $data['variable_type'] ?? 'number';

        $data['components'] = $components;
        $data['variable_samples'] = $variableSamples;
        $data['last_synced_at'] = now();

        return static::getModel()::create($data);
    }

    /**
     * Customize the default success notification.
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Template Submitted ✅')
            ->body('Template has been submitted to Meta for review.')
            ->success();
    }

    /**
     * Redirect to the list templates page after creation.
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
