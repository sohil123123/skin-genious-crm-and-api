<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Clinic;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use BackedEnum;

class LoyaltyPoints extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * Clients at or above this balance are worth a personal call: their rows
     * are highlighted and get contact actions.
     */
    public const CONTACT_THRESHOLD = 3000;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Loyalty Points';

    protected static ?string $title = 'Loyalty Points';

    // Right after Packages (5). Invoices also uses 6 but sits in the Finance
    // group, which is sorted separately from ungrouped items.
    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'loyalty-points';

    protected string $view = 'filament.pages.loyalty-points';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole([
            config('project.roles.super_admin'),
            config('project.roles.clinic_manager'),
            config('project.roles.clinic_head'),
        ]) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::baseQuery()
            ->where('loyalty_points', '>=', static::CONTACT_THRESHOLD)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Clients with ' . number_format(static::CONTACT_THRESHOLD) . '+ points to contact';
    }

    /**
     * Clients only, limited to the viewer's clinic unless they are a super admin.
     */
    protected static function baseQuery(): Builder
    {
        return User::query()
            ->role(config('project.roles.client'))
            ->when(
                !check_role(config('project.roles.super_admin')),
                fn (Builder $query) => $query->where('clinic_id', auth()->user()->clinic_id),
            );
    }

    protected static function isHighValue(User $record): bool
    {
        return (int) $record->loyalty_points >= static::CONTACT_THRESHOLD;
    }

    /**
     * wa.me needs the number with its country code; bare 10-digit numbers are
     * assumed to be Indian.
     */
    protected static function whatsAppUrl(?string $mobile): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $mobile);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        return 'https://wa.me/' . $digits;
    }

    public function table(Table $table): Table
    {
        return $table
            // Clients with no points have nothing to act on, so they are left out.
            ->query(static::baseQuery()->where('loyalty_points', '>', 0)->with('clinic'))
            ->deferLoading()
            ->defaultSort('loyalty_points', 'desc')
            ->recordUrl(null)
            ->recordClasses(fn (User $record): ?string => static::isHighValue($record)
                ? 'fi-row-loyalty-high'
                : null)
            ->columns([
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->placeholder('Unassigned')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => check_role(config('project.roles.super_admin'))),

                TextColumn::make('first_name')
                    ->label('Client Name')
                    ->formatStateUsing(fn (User $record): string => trim($record->first_name . ' ' . ($record->last_name ?? '')))
                    ->icon(fn (User $record): ?string => static::isHighValue($record) ? 'heroicon-s-star' : null)
                    ->iconColor('warning')
                    ->weight(fn (User $record): ?string => static::isHighValue($record) ? 'bold' : null)
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('first_name', $direction)
                        ->orderBy('last_name', $direction))
                    ->searchable(['first_name', 'last_name']),

                // Phone and email stacked in one column, each copyable on its own.
                TextColumn::make('contact')
                    ->label('Contact')
                    ->state(fn (User $record): array => array_values(array_filter(
                        [$record->mobile, $record->email],
                        fn ($value): bool => filled($value),
                    )))
                    ->listWithLineBreaks()
                    ->icon(fn ($state, User $record): string => $state === $record->email
                        ? 'heroicon-m-envelope'
                        : 'heroicon-m-phone')
                    ->copyable()
                    ->copyMessage(fn ($state, User $record): string => $state === $record->email
                        ? 'Email copied'
                        : 'Phone copied')
                    ->placeholder('-')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('mobile', $direction))
                    ->searchable(['mobile', 'email']),

                TextColumn::make('loyalty_points')
                    ->label('Loyalty Points')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state): string => (int) $state >= static::CONTACT_THRESHOLD ? 'success' : 'info')
                    ->icon(fn ($state): ?string => (int) $state >= static::CONTACT_THRESHOLD ? 'heroicon-m-trophy' : null)
                    ->formatStateUsing(fn ($state): string => number_format((int) $state) . ' pts')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('points_tier')
                    ->label('Points Tier')
                    ->options([
                        'contact' => number_format(static::CONTACT_THRESHOLD) . '+ points (contact)',
                        'below' => 'Below ' . number_format(static::CONTACT_THRESHOLD) . ' points',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'contact' => $query->where('loyalty_points', '>=', static::CONTACT_THRESHOLD),
                        'below' => $query->where('loyalty_points', '<', static::CONTACT_THRESHOLD),
                        default => $query,
                    }),

                Filter::make('points_range')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('min')->label('Min Points')->numeric()->minValue(0),
                            TextInput::make('max')->label('Max Points')->numeric()->minValue(0),
                        ]),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['min'] ?? null), fn (Builder $q) => $q->where('loyalty_points', '>=', (int) $data['min']))
                        ->when(filled($data['max'] ?? null), fn (Builder $q) => $q->where('loyalty_points', '<=', (int) $data['max'])))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (filled($data['min'] ?? null)) {
                            $indicators[] = 'Min: ' . number_format((int) $data['min']) . ' pts';
                        }
                        if (filled($data['max'] ?? null)) {
                            $indicators[] = 'Max: ' . number_format((int) $data['max']) . ' pts';
                        }
                        return $indicators;
                    }),

                TernaryFilter::make('opt_for_loyalty')
                    ->label('Opted for Loyalty'),

                SelectFilter::make('clinic_id')
                    ->label('Clinic')
                    ->options(fn () => Clinic::query()->active()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn () => check_role(config('project.roles.super_admin'))),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(1)
            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->recordActions([
                // Only the clients worth a personal call get contact options.
                ActionGroup::make([
                    Action::make('call')
                        ->label('Call')
                        ->icon('heroicon-o-phone')
                        ->color('success')
                        ->url(fn (User $record): string => 'tel:' . $record->mobile)
                        ->visible(fn (User $record): bool => filled($record->mobile)),

                    Action::make('whatsapp')
                        ->label('WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->url(fn (User $record): ?string => static::whatsAppUrl($record->mobile), shouldOpenInNewTab: true)
                        ->visible(fn (User $record): bool => filled(static::whatsAppUrl($record->mobile))),

                    Action::make('email')
                        ->label('Email')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->url(fn (User $record): string => 'mailto:' . $record->email)
                        ->visible(fn (User $record): bool => filled($record->email)),
                ])
                    ->label('Contact')
                    ->icon('heroicon-o-phone-arrow-up-right')
                    ->color('warning')
                    ->button()
                    ->visible(fn (User $record): bool => static::isHighValue($record)),

                Action::make('history')
                    ->label('History')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->url(fn (User $record): string => UserResource::getUrl('loyalty_points', ['record' => $record])),
            ])
            ->emptyStateHeading('No clients found')
            ->emptyStateIcon('heroicon-o-gift');
    }
}
