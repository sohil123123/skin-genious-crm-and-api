<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Actions;

use App\Models\Call;
use App\Models\CallAnalysis;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

/**
 * Read a call's AI analysis without leaving the list.
 *
 * The summary and the next step are the two things anyone actually wants from a
 * row, and both were a page load away. The AI badge on the card opens this.
 *
 * Every figure here is labelled as the model's reading, for the same reason the
 * call page marks its section that way: an inference rendered like a record is
 * a claim the CRM never made. The booking line especially — a green tick beside
 * a call where nobody booked anything is how this went wrong once already.
 */
final class ViewAnalysisAction
{
    public static function make(string $name = 'viewAnalysis'): Action
    {
        return Action::make($name)
            ->label('View AI analysis')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->authorize(fn (?Call $record): bool => $record !== null
                && (auth()->user()?->can('view', $record) ?? false))
            ->visible(fn (?Call $record): bool => $record?->currentAnalysis !== null)
            ->modalHeading(fn (Call $record): string => 'AI analysis — ' . $record->customer_name)
            ->modalDescription(fn (Call $record): string => sprintf(
                '%s · %s · the model’s reading of the transcript, not a clinic record',
                $record->currentAnalysis?->analysis_version ?? 'unknown version',
                $record->currentAnalysis?->model ?? 'unknown model',
            ))
            ->modalContent(fn (Call $record): HtmlString => static::render($record->currentAnalysis))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalWidth('2xl');
    }

    protected static function render(?CallAnalysis $analysis): HtmlString
    {
        if ($analysis === null) {
            return new HtmlString('<p>Nothing analysed for this call.</p>');
        }

        $rows = array_filter([
            'Intent' => $analysis->customer_intent,
            'Outcome' => $analysis->outcome,
            'Sentiment' => $analysis->sentiment?->value,
            'Temperature' => $analysis->lead_temperature,
            'Purchase intent' => $analysis->purchase_intent === null ? null : $analysis->purchase_intent . ' / 100',
            'Urgency' => $analysis->urgency,
            'Treatment interest' => $analysis->treatment_interest,
            'Product interest' => $analysis->product_interest,
            'Objection' => $analysis->objection,
            // Words, not a tick. See the class docblock.
            'Appointment (per AI)' => match ($analysis->appointment_booked) {
                true => 'Booked on the call',
                false => $analysis->appointment_discussed === true ? 'Discussed, not booked' : 'Not booked',
                default => 'Not stated',
            },
        ], static fn (mixed $value): bool => filled($value));

        $html = '<div style="font-size:.875rem; line-height:1.6; max-height:60vh; overflow-y:auto;">';

        if (filled($analysis->summary)) {
            $html .= '<p style="margin:0 0 1rem;">' . e($analysis->summary) . '</p>';
        }

        $html .= '<dl style="display:grid; grid-template-columns:max-content 1fr; gap:.35rem 1rem; margin:0;">';

        foreach ($rows as $label => $value) {
            $html .= '<dt style="color:#6b7280;">' . e($label) . '</dt>';
            $html .= '<dd style="margin:0;">' . e((string) $value) . '</dd>';
        }

        $html .= '</dl>';

        if (filled($analysis->next_best_action)) {
            $html .= '<p style="margin:1rem 0 0;"><strong>Suggested next step</strong><br>'
                . e($analysis->next_best_action) . '</p>';
        }

        return new HtmlString($html . '</div>');
    }
}
