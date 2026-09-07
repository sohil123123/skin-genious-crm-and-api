<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Actions;

use App\Models\Call;
use App\Services\Call\CallIngestionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Run automatic matching over this call again.
 *
 * Useful because the CRM changes underneath its calls: a caller who was a
 * stranger last week is a patient today, and nothing revisits an unmatched call
 * to notice. One press reattaches every call from that number, not just this
 * one, which is why an "Unmatched" row usually clears several at once.
 *
 * Hidden once a person has decided the match by hand. The resolver refuses to
 * touch those by design — a human answer outranks a phone-number guess — so the
 * button would do nothing and imply otherwise.
 */
final class RematchCallCustomerAction
{
    public static function make(string $name = 'rematch'): Action
    {
        return Action::make($name)
            ->label('Re-run customer matching')
            ->icon('heroicon-o-user-plus')
            ->authorize(fn (?Call $record): bool => $record !== null
                && (auth()->user()?->can('matchCustomer', $record) ?? false))
            ->visible(fn (?Call $record): bool => $record?->matching_status?->isHumanDecided() !== true)
            ->action(function (Call $record, $livewire): void {
                $matched = app(CallIngestionService::class)
                    ->rematchUnmatched($record->client_phone_key, limit: 50);

                $record->refresh();

                $record->isMatched()
                    ? Notification::make()
                        ->success()
                        ->title('Customer found')
                        ->body(sprintf('%d call(s) with this number were attached.', max(1, $matched)))
                        ->send()
                    : Notification::make()
                        ->info()
                        ->title('Still no match')
                        ->body('No patient or lead in the CRM has this number.')
                        ->send();

                $livewire->dispatch('$refresh');
            });
    }
}
