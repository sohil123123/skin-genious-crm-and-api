<?php

namespace App\Filament\Resources\AvailabilityExceptions\Pages;

use Filament\Schemas\Components\Section;
use App\Filament\Resources\AvailabilityExceptions\Schemas\AvailabilityExceptionForm;

class CreateAvailabilityExceptionSimple extends BaseCreateAvailabilityException
{
    // protected function getFormSchema(): array
    // {
    //     return [
    //         Section::make()
    //             ->schema([
    //                 ...AvailabilityExceptionForm::getTypeComponents(),
    //                 ...AvailabilityExceptionForm::getClinicAndTherapistComponents(),
    //             ])
    //             ->columns(),
    //     ];
    // }
}
