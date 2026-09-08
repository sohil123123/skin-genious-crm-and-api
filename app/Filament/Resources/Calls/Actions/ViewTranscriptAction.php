<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Actions;

use App\Models\Call;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

/**
 * Read a call's transcript without leaving the list.
 *
 * Reviewing calls means sampling a lot of them, and most of that sampling is
 * answering "what was this one about" — a question the transcript answers in
 * five seconds and a page load answers in thirty. So the Text badge on the card
 * opens this, and the row menu carries it too.
 *
 * A separate grant from viewing the call: knowing that somebody rang is roster
 * information, and reading back what they said about their skin is not.
 */
final class ViewTranscriptAction
{
    public static function make(string $name = 'viewTranscript'): Action
    {
        return Action::make($name)
            ->label('View transcript')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->authorize(fn (?Call $record): bool => $record !== null
                && (auth()->user()?->can('viewTranscript', $record) ?? false))
            ->visible(fn (?Call $record): bool => $record?->hasTranscript() ?? false)
            ->modalHeading(fn (Call $record): string => 'Transcript — ' . $record->customer_name)
            ->modalDescription(fn (Call $record): ?string => $record->currentTranscription === null ? null : implode(' · ', array_filter([
                $record->currentTranscription->provider,
                $record->currentTranscription->model,
                $record->currentTranscription->language,
                $record->currentTranscription->word_count !== null
                    ? $record->currentTranscription->word_count . ' words'
                    : null,
            ])))
            ->modalContent(fn (Call $record): HtmlString => new HtmlString(
                '<div style="white-space:pre-wrap; line-height:1.7; font-size:.875rem; max-height:60vh; overflow-y:auto;">'
                . e((string) $record->currentTranscription?->transcript)
                . '</div>'
            ))
            // Nothing to submit: this is a reading surface, and an "OK" button
            // beside a "Cancel" would imply the two do different things.
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('2xl');
    }
}
