<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\User;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Clients who came in for exactly one session and have not been back.
 *
 * A "session" is an attended visit (completed or in progress) of any type
 * other than a consult — imported history from the old CRM arrives as type
 * "other", so restricting to "treatment" would miss nearly everybody. The
 * count is taken across every clinic, so somebody seen once in each of two
 * branches is not a single-session client in either.
 */
class SingleSessionClients extends TableWidget
{
    /** Session-gap presets offered in the filter, keyed by filter value. */
    public const GAP_30_PLUS = '30_plus';
    public const GAP_30_45 = '30_45';
    public const GAP_45_PLUS = '45_plus';
    public const GAP_45_90 = '45_90';
    public const GAP_90_PLUS = '90_plus';

    /** Session key the WhatsApp campaign create page reads its audience from. */
    public const CAMPAIGN_PREFILL_SESSION_KEY = 'whatsapp_campaign_prefill_user_ids';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Single Session — Not Returned';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    public static function gapOptions(): array
    {
        return [
            self::GAP_30_PLUS => '30+ days ago',
            self::GAP_30_45 => '30 – 45 days ago (due now)',
            self::GAP_45_PLUS => '45+ days ago',
            self::GAP_45_90 => '45 – 90 days ago',
            self::GAP_90_PLUS => '90+ days ago',
        ];
    }

    /**
     * [min days, max days|null] since the session for a gap preset.
     */
    protected static function gapBounds(?string $gap): array
    {
        return match ($gap) {
            self::GAP_30_45 => [30, 45],
            self::GAP_45_PLUS => [45, null],
            self::GAP_45_90 => [45, 90],
            self::GAP_90_PLUS => [90, null],
            default => [30, null],
        };
    }

    /**
     * One row per client who has attended exactly one session, carrying that
     * session's details. With a count of one, MAX() is simply the value.
     */
    protected static function sessionSummaryQuery(): QueryBuilder
    {
        return Appointment::query()
            ->toBase()
            ->select('user_id')
            ->selectRaw('MAX(start_datetime) as session_at')
            ->selectRaw('MAX(clinic_id) as session_clinic_id')
            ->selectRaw('MAX(type) as session_type')
            ->whereNotNull('user_id')
            ->whereIn('status', [AppointmentStatus::Completed->value, AppointmentStatus::InProgress->value])
            ->where('type', '!=', AppointmentType::Consult->value)
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) = 1');
    }

    protected static function viewerClinicId(): ?int
    {
        $user = auth()->user();

        return ($user && !$user->hasRole('super_admin') && $user->clinic_id) ? (int) $user->clinic_id : null;
    }

    protected static function isSuperAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin');
    }

    /**
     * Count shown on the dashboard tab: the default view (30+ days, not booked).
     */
    public static function pendingCount(): int
    {
        return static::baseQuery()
            ->where('ss.session_at', '<', Carbon::today()->subDays(29)->startOfDay())
            ->count();
    }

    /**
     * Single-session clients whose session is in the past and who have nothing
     * booked. The day-gap itself is applied by the filter.
     */
    protected static function baseQuery(): Builder
    {
        $clinicId = static::viewerClinicId();

        return User::query()
            ->role('client')
            ->where('users.is_active', true)
            ->joinSub(static::sessionSummaryQuery(), 'ss', 'ss.user_id', '=', 'users.id')
            ->select('users.*', 'ss.session_at', 'ss.session_clinic_id', 'ss.session_type')
            ->when($clinicId, fn (Builder $q) => $q->where('ss.session_clinic_id', $clinicId))
            // Anyone already coming in is not ours to chase. Same definition as
            // the action engine, via Appointment::scopeCountsAsBooked().
            ->whereNotExists(fn (QueryBuilder $q) => $q
                ->from('appointments as upcoming')
                ->whereColumn('upcoming.user_id', 'users.id')
                ->where('upcoming.start_datetime', '>=', Carbon::today())
                ->whereNotIn('upcoming.status', [
                    AppointmentStatus::Cancelled->value,
                    AppointmentStatus::NoShow->value,
                ]));
    }

    protected static function daysSince(User $record): int
    {
        return (int) Carbon::parse($record->session_at)->startOfDay()->diffInDays(Carbon::today());
    }

    protected static function clinicName(User $record): ?string
    {
        static $names = null;
        $names ??= Clinic::query()->pluck('name', 'id');

        return $names[$record->session_clinic_id] ?? null;
    }

    /**
     * wa.me link with a ready-to-send nudge; bare 10-digit numbers are assumed
     * to be Indian.
     */
    protected static function whatsAppUrl(User $record): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $record->mobile);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        $clinic = static::clinicName($record);
        $message = "Hi {$record->first_name}, it's been " . static::daysSince($record) . " days since your session"
            . ($clinic ? " at {$clinic}" : '')
            . ". For the best results, we recommend booking your next session. Would you like us to schedule it for you?";

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::baseQuery())
            ->defaultSort('session_at', 'desc')
            ->recordUrl(null)
            ->columns([
                TextColumn::make('first_name')
                    ->label('Client')
                    ->formatStateUsing(fn (User $record): string => trim($record->first_name . ' ' . ($record->last_name ?? '')))
                    ->weight('bold')
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('users.first_name', $direction)
                        ->orderBy('users.last_name', $direction))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $match): Builder => $match
                            ->where('users.first_name', 'like', "%{$search}%")
                            ->orWhere('users.last_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT_WS(' ', users.first_name, users.last_name) LIKE ?", ["%{$search}%"])
                            ->orWhere('users.mobile', 'like', '%' . static::phoneNeedle($search) . '%')
                            ->orWhere('users.email', 'like', "%{$search}%")
                    )),

                TextColumn::make('mobile')
                    ->label('Mobile')
                    ->icon('heroicon-m-phone')
                    ->copyable()
                    ->copyMessage('Phone copied')
                    ->placeholder('-'),

                TextColumn::make('session_clinic_id')
                    ->label('Clinic')
                    ->formatStateUsing(fn (User $record): ?string => static::clinicName($record))
                    ->badge()
                    ->color('info')
                    ->visible(fn (): bool => static::isSuperAdmin()),

                TextColumn::make('session_type')
                    ->label('Session')
                    ->formatStateUsing(fn ($state): string => AppointmentType::tryFrom((string) $state)?->getLabel() ?? ucfirst((string) $state))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('session_at')
                    ->label('Session Date')
                    ->date('d M Y')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('ss.session_at', $direction)),

                TextColumn::make('days_since')
                    ->label('Days Since')
                    ->state(fn (User $record): int => static::daysSince($record))
                    ->formatStateUsing(fn (int $state): string => "{$state} days")
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 90 => 'gray',
                        $state >= 45 => 'danger',
                        default => 'warning',
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('ss.session_at', $direction === 'asc' ? 'desc' : 'asc')),
            ])
            ->filters([
                SelectFilter::make('gap')
                    ->label('Session taken')
                    ->options(static::gapOptions())
                    ->default(self::GAP_30_PLUS)
                    ->selectablePlaceholder(false)
                    ->query(function (Builder $query, array $data): Builder {
                        [$min, $max] = static::gapBounds($data['value'] ?? null);

                        return $query
                            ->where('ss.session_at', '<', Carbon::today()->subDays($min - 1)->startOfDay())
                            ->when($max, fn (Builder $q) => $q->where('ss.session_at', '>=', Carbon::today()->subDays($max)->startOfDay()));
                    }),

                Filter::make('session_date')
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('from')->label('Session from')->native(false)->placeholder('Select start date'),
                            DatePicker::make('until')->label('Session until')->native(false)->placeholder('Select end date'),
                        ]),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('ss.session_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('ss.session_at', '<=', Carbon::parse($date)->endOfDay())))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'From ' . Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until ' . Carbon::parse($data['until'])->format('d M Y');
                        }
                        return $indicators;
                    }),

                SelectFilter::make('session_type')
                    ->label('Session type')
                    ->options(collect(AppointmentType::cases())
                        ->reject(fn (AppointmentType $type) => $type === AppointmentType::Consult)
                        ->mapWithKeys(fn (AppointmentType $type) => [$type->value => $type->getLabel()])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, $type) => $q->where('ss.session_type', $type))),

                SelectFilter::make('clinic')
                    ->label('Clinic')
                    ->options(fn () => Clinic::query()->active()->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, $clinicId) => $q->where('ss.session_clinic_id', $clinicId)))
                    ->visible(fn (): bool => static::isSuperAdmin()),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['md' => 2, 'xl' => 4])
            ->recordActions([
                ActionGroup::make([
                    Action::make('whatsapp')
                        ->label('WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->url(fn (User $record): ?string => static::whatsAppUrl($record), shouldOpenInNewTab: true)
                        ->visible(fn (User $record): bool => filled($record->mobile)),

                    Action::make('call')
                        ->label('Call')
                        ->icon('heroicon-o-phone')
                        ->color('info')
                        ->url(fn (User $record): string => 'tel:' . $record->mobile)
                        ->visible(fn (User $record): bool => filled($record->mobile)),

                    Action::make('profile')
                        ->label('View Profile')
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->url(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]), shouldOpenInNewTab: true),
                ])
                    ->label('Contact')
                    ->icon('heroicon-o-phone-arrow-up-right')
                    ->color('primary')
                    ->button(),
            ])
            ->toolbarActions([
                BulkAction::make('whatsapp_campaign')
                    ->label('Create WhatsApp Campaign')
                    ->icon('heroicon-o-megaphone')
                    ->color('success')
                    ->visible(fn (): bool => WhatsAppCampaignResource::canCreate())
                    ->action(function (Collection $records) {
                        session()->put(
                            self::CAMPAIGN_PREFILL_SESSION_KEY,
                            $records->filter(fn (User $user) => filled($user->mobile))->pluck('id')->values()->all(),
                        );

                        return redirect(WhatsAppCampaignResource::getUrl('create'));
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->emptyStateHeading('No single-session clients in this window')
            ->emptyStateDescription('Everyone who came in once during this period has either returned or is already booked.')
            ->emptyStateIcon('heroicon-o-user-minus');
    }

    /**
     * Reduce a typed phone number to the digits actually stored, matching on
     * the trailing national digits so +91 / leading-zero forms both hit.
     */
    protected static function phoneNeedle(string $search): string
    {
        $digits = preg_replace('/\D+/', '', $search) ?? '';

        if ($digits === '' || strlen($digits) < 4) {
            return $search;
        }

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
