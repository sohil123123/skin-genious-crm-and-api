<?php

namespace App\Filament\Resources\AvailabilityExceptions\Pages;

use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Section;
use App\Filament\Resources\AvailabilityExceptions\Schemas\AvailabilityExceptionForm;

class CreateAvailabilityExceptionWizard extends BaseCreateAvailabilityException
{
    use HasWizard;

    protected function getSteps(): array
    {
        return [
            Step::make('Applies To')
                ->schema([
                    Section::make()
                        ->schema(
                            AvailabilityExceptionForm::getTypeComponents()
                        )
                        ->columns(),
                ]),

            Step::make('Availability Details')
                ->schema([
                    Section::make()
                        ->schema(
                            AvailabilityExceptionForm::getClinicAndTherapistComponents()
                        )
                        ->columns(1),
                ]),
        ];
    }
}
