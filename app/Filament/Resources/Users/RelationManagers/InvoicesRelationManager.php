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
use Filament\Notifications\Notification;

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
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label('Create Invoice')
                    ->modalHeading('Create Invoice')
                    ->modalWidth('7xl')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Invoice Created!')
                            ->body('The invoice has been successfully created for the client.')
                    )
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['clinic_id'] = $this->getOwnerRecord()->clinic_id;
                        $data['created_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->latest());
    }
}
