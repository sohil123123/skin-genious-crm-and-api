<?php

namespace App\Filament\Resources\AvailabilityExceptions\Pages;

use Filament\Resources\Pages\Page;
use App\Filament\Resources\AvailabilityExceptions\AvailabilityExceptionResource;

class CreateAvailabilityExceptionEntry extends Page
{
    protected static string $resource = AvailabilityExceptionResource::class;

    protected static ?string $title = 'Create Availability Exception';

    public function mount()
    {
        if (check_role('super_admin') || check_role(['clinic_manager', 'clinic_head'])) {
           return redirect()->to(
                CreateAvailabilityExceptionWizard::getUrl()
            );
        }

        return redirect()->to(
            CreateAvailabilityExceptionSimple::getUrl()
        );
    }
}
