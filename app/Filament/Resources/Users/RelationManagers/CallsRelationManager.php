<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Calls\CallResource;
use App\Filament\Resources\Calls\Schemas\CallInfolist;
use App\Filament\Resources\Calls\Tables\CallsTable;
use App\Models\Call;
use App\Models\User;
use App\Services\Call\CallAnalyticsService;
use App\Services\Call\PhoneNumberNormalizer;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Every call with this patient, in one chronological list.
 *
 * The relationship is deliberately widened beyond customer_user_id. A patient's
 * calls include the ones that arrived before the CRM knew who they were — a
 * lead who later became a patient, or a number matched weeks after the fact —
 * and a history that quietly omitted those would be worse than no history,
 * because staff would trust it.
 *
 * Nothing here distinguishes Exotel from Callyzer. Reception sees conversations
 * with a person, which is the entire purpose of the unified layer.
 */
class CallsRelationManager extends RelationManager
{
    protected static string $relationship = 'calls';

    protected static ?string $title = 'Calls';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-phone';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', Call::class) ?? false;
    }

    public function infolist(Schema $schema): Schema
    {
        return CallInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return CallsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->scopeToPerson($query))
            ->heading(fn (): HtmlString => $this->summaryHeading())
            ->headerActions([
                Action::make('openCallList')
                    ->label('Open in Calls')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (): string => CallResource::getUrl('index', [
                        'tableFilters' => [
                            'customer_user_id' => ['value' => $this->getOwnerRecord()->getKey()],
                        ],
                    ])),
            ])
            ->defaultSort('started_at', 'desc')
            ->emptyStateHeading('No calls with this patient yet');
    }

    /**
     * Widen the relation to every call that is really this person's.
     *
     * The phone key is what catches calls recorded before the match existed.
     * Without it a lead who converts appears never to have been spoken to.
     */
    protected function scopeToPerson(Builder $query): Builder
    {
        /** @var User $user */
        $user = $this->getOwnerRecord();

        $key = app(PhoneNumberNormalizer::class)->matchKey($user->mobile);

        // The relation already constrains to customer_user_id. This adds the
        // second way a call can be theirs, as its own bracketed group — a bare
        // orWhere here would sit outside the soft-delete guard and start
        // showing deleted calls.
        if (filled($key)) {
            $query->orWhere(fn (Builder $inner): Builder => $inner
                ->where('client_phone_key', $key)
                ->whereNull('deleted_at'));
        }

        return $query->with([
            'agent:id,first_name,last_name',
            'clinic:id,name',
            'lead:id,full_name,phone',
        ]);
    }

    /**
     * A one-line engagement summary above the list.
     *
     * These are the same figures the Next Best Action engine reads, computed by
     * the same service — so what a staff member sees on this screen and what an
     * automated rule acts on can never disagree.
     */
    protected function summaryHeading(): HtmlString
    {
        /** @var User $user */
        $user = $this->getOwnerRecord();

        $stats = app(CallAnalyticsService::class)->forUser($user);

        if ($stats['total_calls'] === 0) {
            return new HtmlString('Calls');
        }

        $parts = [
            sprintf('<strong>%d</strong> calls', $stats['total_calls']),
            sprintf('%d in · %d out', $stats['incoming_calls'], $stats['outgoing_calls']),
            sprintf('<strong>%d</strong> connected', $stats['connected_calls']),
        ];

        if ($stats['missed_calls'] > 0) {
            $parts[] = sprintf('%d missed', $stats['missed_calls']);
        }

        if ($stats['total_talk_seconds'] > 0) {
            $parts[] = sprintf('%d min talk time', (int) round($stats['total_talk_seconds'] / 60));
        }

        if ($stats['last_call_at'] !== null) {
            $parts[] = 'last called ' . $stats['last_call_at']->diffForHumans();
        }

        // Called only when it is true and actionable: a run of unanswered
        // attempts is the signal to try a different channel, and it is easy to
        // miss in a list of rows.
        if ($stats['consecutive_unanswered'] >= 3) {
            $parts[] = sprintf(
                '<span style="color:var(--warning-600);">%d unanswered in a row</span>',
                $stats['consecutive_unanswered'],
            );
        }

        return new HtmlString(
            '<span style="font-size:.8125rem;font-weight:400;">' . implode(' · ', $parts) . '</span>'
        );
    }
}
