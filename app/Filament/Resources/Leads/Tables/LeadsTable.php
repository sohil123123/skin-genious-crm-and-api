<?php

declare(strict_types=1);

namespace App\Filament\Resources\Leads\Tables;

use App\Actions\Lead\AssignLeadsAction;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\PhoneStatus;
use App\Filament\Exports\LeadExporter;
use App\Models\Lead;
use App\Models\LeadCustomField;
use App\Models\LeadImport;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->description(fn (Lead $record): ?string => $record->city)
                    ->searchable(['full_name', 'first_name', 'last_name'])
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->icon(fn (Lead $record): ?string => $record->phone_status === PhoneStatus::NeedsReview
                        ? 'heroicon-o-exclamation-triangle'
                        : null)
                    ->iconColor('warning')
                    // The tooltip carries the original value so a salvaged
                    // number can be checked against what Facebook actually sent
                    // without opening the record.
                    ->tooltip(fn (Lead $record): ?string => $record->phone_status === PhoneStatus::NeedsReview
                        ? 'Repaired from: ' . $record->phone_raw
                        : null),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('matched_user_id')
                    ->label('Patient')
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-identification')
                    ->formatStateUsing(fn (): string => 'Existing')
                    ->placeholder('—')
                    ->tooltip(fn (Lead $record): ?string => $record->matchedUser
                        ? 'Matches patient: ' . $record->matchedUser->name
                        : null)
                    ->url(fn (Lead $record): ?string => $record->matched_user_id
                        ? \App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $record->matched_user_id])
                        : null),

                TextColumn::make('campaign_name')
                    ->label('Campaign')
                    ->limit(30)
                    ->tooltip(fn (Lead $record): ?string => $record->campaign_name)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('form_name')
                    ->label('Form')
                    ->limit(30)
                    ->tooltip(fn (Lead $record): ?string => $record->form_name)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('adset_name')
                    ->label('Ad set')
                    ->limit(28)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ad_name')
                    ->label('Ad')
                    ->limit(28)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('source')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('assignedStaff.name')
                    ->label('Assigned to')
                    ->placeholder('Unassigned')
                    ->toggleable(),

                TextColumn::make('fb_created_time')
                    ->label('Submitted')
                    ->dateTime(config('leads.display.datetime_format'))
                    ->timezone(config('leads.display.timezone'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Imported')
                    ->dateTime(config('leads.display.datetime_format'))
                    ->timezone(config('leads.display.timezone'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),
            ])
            ->filters(static::filters(), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['default' => 1, 'md' => 2, 'xl' => 3])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign')
                        ->label('Assign to staff')
                        ->icon('heroicon-o-user-plus')
                        ->schema([
                            Select::make('assigned_to')
                                ->label('Assign to')
                                ->options(fn (): array => static::staffOptions())
                                ->searchable()
                                ->placeholder('Unassign'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = app(AssignLeadsAction::class)->assign($records, $data['assigned_to'] ?: null);

                            Notification::make()
                                ->title("{$count} leads reassigned")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('changeStatus')
                        ->label('Change status')
                        ->icon('heroicon-o-flag')
                        ->schema([
                            Select::make('status')
                                ->label('New status')
                                ->options(LeadStatus::options())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $count = app(AssignLeadsAction::class)->changeStatus($records, $data['status']);

                            Notification::make()
                                ->title("{$count} leads updated")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    ExportBulkAction::make()
                        ->exporter(LeadExporter::class),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No leads yet')
            ->emptyStateDescription('Import a Facebook lead export to populate this list.')
            ->emptyStateIcon('heroicon-o-user-plus');
    }

    /**
     * @return array<int, \Filament\Tables\Filters\BaseFilter>
     */
    protected static function filters(): array
    {
        $filters = [
            SelectFilter::make('status')
                ->options(LeadStatus::options())
                ->multiple(),

            SelectFilter::make('source')
                ->options(LeadSource::options())
                ->multiple(),

            SelectFilter::make('clinic_id')
                ->label('Clinic')
                ->relationship('clinic', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),

            SelectFilter::make('assigned_to')
                ->label('Assigned to')
                ->options(fn (): array => static::staffOptions())
                ->searchable(),

            // Campaign, ad set, ad and form are free-text columns rather than
            // relationships, so their options are collected from the data that
            // has actually been imported.
            static::distinctColumnFilter('campaign_name', 'Campaign'),
            static::distinctColumnFilter('form_name', 'Form'),
            static::distinctColumnFilter('adset_name', 'Ad set'),
            static::distinctColumnFilter('ad_name', 'Ad'),

            SelectFilter::make('lead_import_id')
                ->label('Import batch')
                ->options(fn (): array => LeadImport::query()
                    ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic())
                    ->latest('id')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (LeadImport $import): array => [
                        $import->getKey() => $import->label ?: $import->original_filename,
                    ])
                    ->all())
                ->searchable(),

            TernaryFilter::make('matched_user_id')
                ->label('Existing patient')
                ->placeholder('All leads')
                ->trueLabel('Matches an existing patient')
                ->falseLabel('New contacts only')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereNotNull('matched_user_id'),
                    false: fn (Builder $query): Builder => $query->whereNull('matched_user_id'),
                    blank: fn (Builder $query): Builder => $query,
                ),

            SelectFilter::make('phone_status')
                ->label('Phone quality')
                ->options(PhoneStatus::options()),

            Filter::make('submitted_between')
                ->schema([
                    DatePicker::make('from')->label('Submitted from'),
                    DatePicker::make('until')->label('Submitted until'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('fb_created_time', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('fb_created_time', '<=', $date))),

            Filter::make('unassigned')
                ->label('Unassigned only')
                ->query(fn (Builder $query): Builder => $query->whereNull('assigned_to'))
                ->toggle(),
        ];

        return array_merge($filters, static::customFieldFilters());
    }

    /**
     * Build a filter for every dynamic question that has a fixed answer set.
     *
     * This is what turns Facebook's form questions into something the clinic can
     * actually segment on — "show me every lead whose main concern is
     * pigmentation" — which a plain text column could never support.
     *
     * @return array<int, SelectFilter>
     */
    protected static function customFieldFilters(): array
    {
        $fields = LeadCustomField::query()
            ->where('is_active', true)
            ->whereIn('type', ['select', 'multiselect'])
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic())
            ->orderByDesc('usage_count')
            // Every question becoming a filter would overwhelm the panel, so
            // only the most-answered ones are surfaced.
            ->limit(8)
            ->get();

        $filters = [];

        foreach ($fields as $field) {
            $options = $field->option_list;

            if ($options === []) {
                continue;
            }

            $filters[] = SelectFilter::make('custom_field_' . $field->key)
                ->label(\Illuminate\Support\Str::limit($field->display_label, 45))
                ->options($options)
                ->multiple()
                ->query(function (Builder $query, array $data) use ($field): Builder {
                    $values = $data['values'] ?? [];

                    if ($values === []) {
                        return $query;
                    }

                    return $query->whereHas('fieldValues', function (Builder $inner) use ($field, $values): void {
                        $inner->where('lead_custom_field_id', $field->getKey())
                            ->where(function (Builder $match) use ($values): void {
                                foreach ($values as $value) {
                                    // Multi-answer rows keep every choice in one
                                    // pipe-joined cell, so an equality test alone
                                    // would miss a lead who picked this option
                                    // alongside others.
                                    $match->orWhere('value', $value)
                                        ->orWhere('value', 'like', $value . '|%')
                                        ->orWhere('value', 'like', '%|' . $value . '|%')
                                        ->orWhere('value', 'like', '%|' . $value);
                                }
                            });
                    });
                });
        }

        return $filters;
    }

    protected static function distinctColumnFilter(string $column, string $label): SelectFilter
    {
        return SelectFilter::make($column)
            ->label($label)
            ->options(fn (): array => Lead::query()
                ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic())
                ->whereNotNull($column)
                ->distinct()
                ->orderBy($column)
                ->limit(200)
                ->pluck($column, $column)
                ->all())
            ->multiple()
            ->searchable();
    }

    /**
     * @return array<int, string>
     */
    protected static function staffOptions(): array
    {
        return User::query()
            ->withoutGlobalScopes()
            ->when(
                ! check_role(config('project.roles.super_admin')),
                fn (Builder $query) => $query->where('clinic_id', auth()->user()?->clinic_id)
            )
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                config('project.roles.clinic_manager'),
                config('project.roles.clinic_head'),
                config('project.roles.therapist'),
                config('project.roles.super_admin'),
            ]))
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->name])
            ->all();
    }
}
