<?php

namespace App\Filament\Resources\WhatsAppTemplates\Pages;

use App\Filament\Resources\WhatsAppTemplates\WhatsAppTemplateResource;
use App\Services\WhatsAppTemplateLibraryService;
use Filament\Resources\Pages\Page;

class WhatsAppTemplateLibrary extends Page
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected string $view = 'filament.pages.whats-app-template-library';

    public string $activeCategory = 'UTILITY';

    public string $search = '';

    public function mount(): void
    {
        $this->activeCategory = request()->query('category', 'UTILITY');
    }

    public function selectCategory(string $category): void
    {
        $this->activeCategory = strtoupper($category);
    }

    public function useTemplate(string $category, string $presetKey): void
    {
        $url = WhatsAppTemplateResource::getUrl('create', [
            'category' => $category,
            'preset' => $presetKey,
        ]);

        $this->redirect($url);
    }

    public function getFilteredTemplates(): array
    {
        $allTemplates = WhatsAppTemplateLibraryService::getTemplates();
        $categoryTemplates = $allTemplates[$this->activeCategory] ?? [];

        if (empty($this->search)) {
            return $categoryTemplates;
        }

        $query = strtolower($this->search);
        return array_filter($categoryTemplates, function ($tpl) use ($query) {
            return str_contains(strtolower($tpl['title']), $query)
                || str_contains(strtolower($tpl['body_text']), $query);
        });
    }

    public function getCategoryCounts(): array
    {
        $allTemplates = WhatsAppTemplateLibraryService::getTemplates();
        return [
            'UTILITY' => count($allTemplates['UTILITY'] ?? []),
            'AUTHENTICATION' => count($allTemplates['AUTHENTICATION'] ?? []),
        ];
    }
}
