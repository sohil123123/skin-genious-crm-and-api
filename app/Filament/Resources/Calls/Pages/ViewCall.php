<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Pages;

use App\Filament\Resources\Calls\Actions\AnalyseCallAction;
use App\Filament\Resources\Calls\Actions\RefreshCallFromProviderAction;
use App\Filament\Resources\Calls\Actions\RematchCallCustomerAction;
use App\Filament\Resources\Calls\Actions\RetryRecordingDownloadAction;
use App\Filament\Resources\Calls\Actions\TranscribeCallAction;
use App\Filament\Resources\Calls\CallResource;
use App\Models\Call;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

/**
 * One call in full.
 *
 * The header actions are the recovery paths for the three things that routinely
 * go wrong on their own — a recording that was not ready when the webhook
 * fired, a transcription that failed transiently, and a customer the matcher
 * could not identify. Each is a retry a staff member can trigger without
 * needing anyone to run a command on a server.
 */
class ViewCall extends ViewRecord
{
    protected static string $resource = CallResource::class;

    public function getTitle(): string
    {
        /** @var Call $record */
        $record = $this->getRecord();

        return sprintf(
            '%s call with %s',
            $record->direction?->getLabel() ?? 'Call',
            $record->customer_name,
        );
    }

    public function getSubheading(): ?string
    {
        /** @var Call $record */
        $record = $this->getRecord();

        return $record->started_at
            ?->timezone(app_timezone())
            ->format(app_datetime_format());
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                RefreshCallFromProviderAction::make(),
                RetryRecordingDownloadAction::make(),
                TranscribeCallAction::make(),
                AnalyseCallAction::make(),
                RematchCallCustomerAction::make(),
            ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
        ];
    }
}
