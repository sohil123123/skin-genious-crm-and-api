<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseReferenceType;
use App\Filament\Exports\ExpenseExporter;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Models\Expense;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expense_date', 'desc')
            ->columns([
                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('clinic.name')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('INR')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('payment_method')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ($state instanceof ExpensePaymentMethod ? $state : ExpensePaymentMethod::tryFrom((string) $state))?->label() ?? ucfirst((string) $state))
                    ->color(fn ($state): string => ($state instanceof ExpensePaymentMethod ? $state : ExpensePaymentMethod::tryFrom((string) $state))?->color() ?? 'gray')
                    ->searchable(),

                TextColumn::make('reference')
                    ->state(fn (Expense $record): string => $record->referenceType()->label() . ($record->reference_id ? " #{$record->reference_id}" : ''))
                    ->badge()
                    ->color(fn (Expense $record): string => $record->reference_type === ExpenseReferenceType::Purchase->value ? 'success' : 'gray')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $query): Builder => $query
                            ->where('reference_type', 'like', "%{$search}%")
                            ->orWhere('reference_id', 'like', "%{$search}%")
                            ->orWhere('reference_number', 'like', "%{$search}%"))),

                TextColumn::make('vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ($state instanceof ExpenseApprovalStatus ? $state : ExpenseApprovalStatus::tryFrom((string) $state))?->label() ?? ucfirst((string) $state))
                    ->color(fn ($state): string => ($state instanceof ExpenseApprovalStatus ? $state : ExpenseApprovalStatus::tryFrom((string) $state))?->color() ?? 'gray')
                    ->sortable(),

                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->limit(35)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('clinic_id')
                    ->relationship('clinic', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),

                SelectFilter::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('payment_method')
                    ->options(ExpensePaymentMethod::options()),

                SelectFilter::make('approval_status')
                    ->label('Approval')
                    ->options(ExpenseApprovalStatus::options()),

                Filter::make('expense_date')
                    ->form([
                        Grid::make(2)->schema([
                            DatePicker::make('from')->label('From'),
                            DatePicker::make('until')->label('Until'),
                        ]),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('expense_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('expense_date', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('From ' . Carbon::parse($data['from'])->toFormattedDateString())
                                ->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('Until ' . Carbon::parse($data['until'])->toFormattedDateString())
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Expense $record): bool => auth()->user()->hasRole('super_admin')
                        && $record->approvalStatus() !== ExpenseApprovalStatus::Approved)
                    ->form([
                        Textarea::make('approval_comment')
                            ->label('Approval Comment')
                            ->rows(3),
                    ])
                    ->action(fn (Expense $record, array $data) => $record->approve($data['approval_comment'] ?? null)),

                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Expense $record): bool => auth()->user()->hasRole('super_admin')
                        && $record->approvalStatus() !== ExpenseApprovalStatus::Rejected)
                    ->form([
                        Textarea::make('approval_comment')
                            ->label('Rejection Comment')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(fn (Expense $record, array $data) => $record->reject($data['approval_comment'] ?? null)),

                EditAction::make()
                    ->schema(ExpenseForm::components())
                    ->modalWidth('5xl')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Expense updated successfully 🎉')
                            ->body('The expense details have been updated.'),
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ExpenseExporter::class)
                        ->label('Export Selected'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
