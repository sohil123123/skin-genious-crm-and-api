<?php

namespace App\Filament\Resources\InvoicePayments;

use App\Filament\Resources\InvoicePayments\Pages\CreateInvoicePayment;
use App\Filament\Resources\InvoicePayments\Pages\EditInvoicePayment;
use App\Filament\Resources\InvoicePayments\Pages\ListInvoicePayments;
use App\Filament\Resources\InvoicePayments\Schemas\InvoicePaymentForm;
use App\Filament\Resources\InvoicePayments\Tables\InvoicePaymentsTable;
use App\Models\InvoicePayment;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicePaymentResource extends Resource
{
    protected static ?string $model = InvoicePayment::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return InvoicePaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicePaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoicePayments::route('/'),
            'create' => CreateInvoicePayment::route('/create'),
            'edit' => EditInvoicePayment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(!check_role(config('project.roles.super_admin')), function ($query) {
                $query->whereHas('invoice', function ($q) {
                    $q->where('clinic_id', auth()->user()->clinic_id);
                });
            });
    }
}
