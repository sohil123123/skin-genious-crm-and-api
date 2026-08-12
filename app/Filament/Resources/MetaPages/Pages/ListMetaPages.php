<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaPages\Pages;

use App\Filament\Resources\MetaPages\MetaPageResource;
use Filament\Resources\Pages\ListRecords;

/**
 * There is deliberately no Create action.
 *
 * Pages register themselves the first time a lead arrives from them, so
 * offering a create button would invite exactly the manual setup step this
 * integration exists to remove — and a hand-typed Page id that does not match
 * what Meta sends would sit there looking configured while quietly receiving
 * nothing.
 */
class ListMetaPages extends ListRecords
{
    protected static string $resource = MetaPageResource::class;
}
