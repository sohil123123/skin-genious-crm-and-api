<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadCustomFields\Pages;

use App\Filament\Resources\LeadCustomFields\LeadCustomFieldResource;
use Filament\Resources\Pages\ListRecords;

class ListLeadCustomFields extends ListRecords
{
    protected static string $resource = LeadCustomFieldResource::class;
}
