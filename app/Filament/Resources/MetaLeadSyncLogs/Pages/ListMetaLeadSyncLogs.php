<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaLeadSyncLogs\Pages;

use App\Filament\Resources\MetaLeadSyncLogs\MetaLeadSyncLogResource;
use Filament\Resources\Pages\ListRecords;

class ListMetaLeadSyncLogs extends ListRecords
{
    protected static string $resource = MetaLeadSyncLogResource::class;
}
