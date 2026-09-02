<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadMappingTemplates\Pages;

use App\Filament\Resources\LeadMappingTemplates\LeadMappingTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListLeadMappingTemplates extends ListRecords
{
    protected static string $resource = LeadMappingTemplateResource::class;
}
