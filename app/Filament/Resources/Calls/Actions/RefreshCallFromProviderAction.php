<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Actions;

use App\Enums\Call\CallProvider;
use App\Models\Call;
use App\Services\Call\CallProviderManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Ask the provider for this call again.
 *
 * The usual reason: Exotel finalises a recording a short while after the call
 * ends, so a flow that hangs up promptly delivers its last webhook with no
 * RecordingUrl. Without a pull, that call permanently has no audio despite one
 * existing.
 *
 * Shared between the call page and the list, because the list is where somebody
 * notices a row with no recording — and having to open the call to fix it makes
 * a page load out of a single click.
 */
final class RefreshCallFromProviderAction
{
    public static function make(string $name = 'refreshFromProvider'): Action
    {
        return Action::make($name)
            ->label('Refresh from provider')
            ->icon('heroicon-o-arrow-path')
            ->authorize(fn (?Call $record): bool => $record !== null
                && (auth()->user()?->can('update', $record) ?? false))
            // Manual calls have no provider to ask.
            ->visible(fn (?Call $record): bool => in_array(
                $record?->provider,
                [CallProvider::Exotel, CallProvider::Callyzer],
                true,
            ))
            ->requiresConfirmation()
            ->modalHeading('Refresh this call')
            ->modalDescription('Fetches this call again from the provider. Anything the CRM recorded — the outcome, the note, a manual match — is left alone.')
            ->modalSubmitActionLabel('Refresh')
            ->action(function (Call $record, $livewire): void {
                $manager = app(CallProviderManager::class);

                try {
                    $refreshed = match ($record->provider) {
                        CallProvider::Exotel => $manager->get(CallProvider::Exotel)->refreshCall($record),
                        CallProvider::Callyzer => $manager->get(CallProvider::Callyzer)
                            ->refreshCalls([$record->provider_call_id]) > 0,
                        default => false,
                    };
                } catch (\Throwable $exception) {
                    // Reported rather than thrown: a provider being unreachable
                    // is an ordinary Tuesday, and an error page teaches people
                    // to stop pressing the button.
                    Notification::make()
                        ->danger()
                        ->title('Could not refresh the call')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                $refreshed
                    ? Notification::make()->success()->title('Call refreshed')->send()
                    : Notification::make()->warning()->title('The provider returned nothing new')->send();

                // Re-renders whichever surface invoked this — the infolist on
                // the call page, the row in the list — so the refreshed values
                // are visible without a manual reload.
                $livewire->dispatch('$refresh');
            });
    }
}
