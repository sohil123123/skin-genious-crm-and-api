<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadCustomFields\Tables;

use App\Enums\LeadFieldType;
use App\Models\LeadCustomField;
use App\Models\LeadFieldValue;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Tables\Enums\FiltersLayout;

class LeadCustomFieldsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('usage_count', 'desc')
            ->columns([
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->color('info')
                    ->placeholder('All clinics')
                    ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),

                TextColumn::make('display_label')
                    ->label('Question')
                    ->wrap()
                    ->limit(110)
                    ->searchable(['label', 'key'])
                    ->weight('medium'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                // Badging an array state renders one badge per element and
                // formats each element in turn, so a formatter that counted the
                // array never ran against the array — it produced a row of
                // blank pills, one per option. The values are listed instead,
                // which is what the column claims to show anyway.
                TextColumn::make('options')
                    ->label('Options')
                    ->badge()
                    ->color('gray')
                    ->limitList(3)
                    ->expandableLimitedList()
                    // Meta snake_cases its answers, so the same humanising the
                    // rest of the module applies is used here rather than a
                    // second, subtly different version of it.
                    ->formatStateUsing(fn ($state): string => Str::limit(
                        LeadCustomField::humanizeValue((string) $state),
                        40
                    ))
                    ->placeholder('—'),

                TextColumn::make('usage_count')
                    ->label('Answers')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('First seen')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(LeadFieldType::options())
                    ->multiple(),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->default(true),

                SelectFilter::make('clinic_id')
                    ->label('Clinic')
                    ->relationship('clinic', 'name', fn (Builder $query) => $query->active()->orderBy('name'))
                    ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(3)
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        // The record title attribute is the raw imported label,
                        // so the default heading reads
                        // "Edit which_session_are_you_interested_in?".
                        ->modalHeading(fn (LeadCustomField $record): string => 'Edit “' . $record->display_label . '”')
                        ->modalDescription('Imported from a lead form. Wording and options can be tidied up; the key answers are stored against cannot.')
                        // Three sections of two columns need more room than the
                        // default modal gives them, which is what forced every
                        // field to stack.
                        ->modalWidth('2xl')
                        ->slideOver(),

                    Action::make('merge')
                        ->label('Merge into another question')
                        ->icon('heroicon-o-arrows-pointing-in')
                        ->color('warning')
                        ->visible(fn (LeadCustomField $record): bool => auth()->user()->can('merge', $record))
                        ->schema(fn (LeadCustomField $record): array => [
                            Select::make('target_id')
                                ->label('Move all answers into')
                                ->options(fn (): array => LeadCustomField::query()
                                    ->whereKeyNot($record->getKey())
                                    ->where('is_active', true)
                                    ->when(
                                        ! check_role(config('project.roles.super_admin')),
                                        fn (Builder $query) => $query->forCurrentClinic()
                                    )
                                    ->orderByDesc('usage_count')
                                    ->get()
                                    ->mapWithKeys(fn (LeadCustomField $field): array => [
                                        $field->getKey() => \Illuminate\Support\Str::limit($field->display_label, 80),
                                    ])
                                    ->all())
                                ->searchable()
                                ->required()
                                ->helperText('Every answer to this question moves to the chosen one, and this question is deactivated.'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Merge questions')
                        ->modalDescription('Use this when the same question was asked with different wording across lead forms, so reporting stays in one place.')
                        ->action(function (LeadCustomField $record, array $data): void {
                            $moved = static::merge($record, (int) $data['target_id']);

                            Notification::make()
                                ->title('Questions merged')
                                ->body("{$moved} answers moved. The original question has been deactivated.")
                                ->success()
                                ->send();
                        }),

                    Action::make('toggleActive')
                        ->label(fn (LeadCustomField $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                        ->icon(fn (LeadCustomField $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                        ->action(fn (LeadCustomField $record) => $record->update(['is_active' => ! $record->is_active])),

                    DeleteAction::make()
                        // Deleting cascades to every stored answer, so it is
                        // only offered for questions nothing depends on.
                        ->visible(fn (LeadCustomField $record): bool => $record->usage_count === 0),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No questions yet')
            ->emptyStateDescription('Questions are registered automatically the first time a lead form containing them is imported.');
    }

    /**
     * Move every answer from one question to another.
     *
     * A lead that already answered the target question keeps that answer — the
     * duplicate from the source is discarded rather than overwriting it, since
     * the target is the field the user chose to keep.
     *
     * @return int Number of answers moved.
     */
    protected static function merge(LeadCustomField $source, int $targetId): int
    {
        return DB::transaction(function () use ($source, $targetId): int {
            $target = LeadCustomField::findOrFail($targetId);

            $leadsWithTarget = LeadFieldValue::query()
                ->where('lead_custom_field_id', $target->getKey())
                ->pluck('lead_id');

            LeadFieldValue::query()
                ->where('lead_custom_field_id', $source->getKey())
                ->whereIn('lead_id', $leadsWithTarget)
                ->delete();

            $moved = LeadFieldValue::query()
                ->where('lead_custom_field_id', $source->getKey())
                ->update(['lead_custom_field_id' => $target->getKey()]);

            $target->forceFill([
                'usage_count' => LeadFieldValue::where('lead_custom_field_id', $target->getKey())->count(),
                'options' => array_values(array_unique(array_merge(
                    $target->options ?? [],
                    $source->options ?? [],
                ))),
            ])->save();

            $source->forceFill([
                'is_active' => false,
                'usage_count' => 0,
            ])->save();

            return $moved;
        });
    }
}
