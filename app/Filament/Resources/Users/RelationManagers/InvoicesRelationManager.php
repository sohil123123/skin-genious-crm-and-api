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
use App\Filament\Resources\Invoices\Schemas\InvoiceInfolist;
use Filament\Schemas\Components\Tabs\Tab;


class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
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
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-document-duplicate')
                ->badge($this->getOwnerRecord()->invoices()->count())
                ->badgeColor('gray'),

            'paid' => Tab::make('Paid')
                ->icon('heroicon-o-check-circle')
                ->query(fn ($query) => $query->where('status', 'paid'))
                ->badge($this->getOwnerRecord()->invoices()->where('status', 'paid')->count())
                ->badgeColor('success'),

            'unpaid' => Tab::make('Unpaid')
                ->icon('heroicon-o-exclamation-circle')
                ->query(fn ($query) => $query->where('status', 'unpaid'))
                ->badge($this->getOwnerRecord()->invoices()->where('status', 'unpaid')->count())
                ->badgeColor('danger'),

            'partial' => Tab::make('Partial')
                ->icon('heroicon-o-clock')
                ->query(fn ($query) => $query->where('status', 'partial'))
                ->badge($this->getOwnerRecord()->invoices()->where('status', 'partial')->count())
                ->badgeColor('warning'),

            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-arrow-path')
                ->query(fn ($query) => $query->where('status', 'pending'))
                ->badge($this->getOwnerRecord()->invoices()->where('status', 'pending')->count())
                ->badgeColor('warning'),

            'draft' => Tab::make('Draft')
                ->icon('heroicon-o-document')
                ->query(fn ($query) => $query->where('status', 'draft'))
                ->badge($this->getOwnerRecord()->invoices()->where('status', 'draft')->count())
                ->badgeColor('gray'),

            'cancelled' => Tab::make('Cancelled')
                ->icon('heroicon-o-x-circle')
                ->query(fn ($query) => $query->where('status', 'cancelled'))
                ->badge($this->getOwnerRecord()->invoices()->where('status', 'cancelled')->count())
                ->badgeColor('danger'),
        ];
    }
}
