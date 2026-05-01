<?php

namespace App\Filament\Exports;

use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseReferenceType;
use App\Models\Expense;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Carbon;

class ExpenseExporter extends Exporter
{
    public static function getModel(): string
    {
        return Expense::class;
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),

            ExportColumn::make('clinic.name')
                ->label('Clinic'),

            ExportColumn::make('category.name')
                ->label('Category'),

            ExportColumn::make('expense_date')
                ->label('Expense Date')
                ->formatStateUsing(fn ($state): string => $state ? Carbon::parse($state)->format('d-m-Y') : ''),

            ExportColumn::make('amount')
                ->label('Amount'),

            ExportColumn::make('payment_method')
                ->label('Payment Method')
                ->formatStateUsing(fn ($state): string => ($state instanceof ExpensePaymentMethod ? $state : ExpensePaymentMethod::tryFrom((string) $state))?->label() ?? ucfirst((string) $state)),

            ExportColumn::make('reference_type')
                ->label('Reference Type')
                ->formatStateUsing(fn ($state): string => ($state instanceof ExpenseReferenceType ? $state : ExpenseReferenceType::tryFrom((string) $state))?->label() ?? ucfirst((string) $state)),

            ExportColumn::make('reference_id')
                ->label('Reference ID'),

            ExportColumn::make('reference_number')
                ->label('Reference Number'),

            ExportColumn::make('vendor_name')
                ->label('Vendor / Payee'),

            ExportColumn::make('approval_status')
                ->label('Approval Status')
                ->formatStateUsing(fn ($state): string => ($state instanceof ExpenseApprovalStatus ? $state : ExpenseApprovalStatus::tryFrom((string) $state))?->label() ?? ucfirst((string) $state)),

            ExportColumn::make('approver.name')
                ->label('Approved By'),

            ExportColumn::make('approved_at')
                ->label('Approved At')
                ->formatStateUsing(fn ($state): string => $state ? Carbon::parse($state)->format('d-m-Y H:i') : ''),

            ExportColumn::make('creator.name')
                ->label('Created By'),

            ExportColumn::make('description')
                ->label('Description'),

            ExportColumn::make('created_at')
                ->label('Created At')
                ->formatStateUsing(fn ($state): string => $state ? Carbon::parse($state)->format('d-m-Y H:i') : ''),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your expense report export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
