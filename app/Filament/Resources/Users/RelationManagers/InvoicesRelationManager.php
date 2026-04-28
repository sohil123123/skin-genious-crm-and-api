<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Models\Invoice;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\CreateAction;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return InvoicesTable::configure($table)
            // ->headerActions([
            //     CreateAction::make()->icon('heroicon-o-plus'),
            // ])
            ->modifyQueryUsing(fn (Builder $query) => $query->latest());
    }
}
