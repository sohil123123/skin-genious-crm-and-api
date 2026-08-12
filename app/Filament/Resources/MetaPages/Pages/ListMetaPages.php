<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaPages\Pages;

use App\Filament\Resources\MetaPages\MetaPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMetaPages extends ListRecords
{
    protected static string $resource = MetaPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Connect a Page'),
        ];
    }
}
