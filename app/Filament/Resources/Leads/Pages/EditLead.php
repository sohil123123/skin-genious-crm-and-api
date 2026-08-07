<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads\Pages;

use App\Enums\PhoneStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Services\Lead\PhoneNormalizerService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * A human editing the phone number is the resolution of the "needs review"
     * flag, so the status is re-derived from whatever they saved.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['phone'] ?? null) !== null) {
            $result = app(PhoneNormalizerService::class)->normalize((string) $data['phone']);

            $data['phone'] = $result->value ?? $data['phone'];
            $data['phone_status'] = $result->isUsable() && $result->status === PhoneStatus::Valid
                ? PhoneStatus::Valid->value
                : $result->status->value;
        }

        return $data;
    }
}
