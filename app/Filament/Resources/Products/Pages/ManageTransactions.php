<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use BackedEnum;
use Filament\Actions\Action;

use App\Filament\Resources\Products\RelationManagers\TransactionsRelationManager;

class ManageTransactions extends ManageRelatedRecords
{
    protected static string $resource = ProductResource::class;

    protected static string $relationship = 'transactions';

    protected static ?string $relatedResource = null;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    // public function mount(int | string $record): void
    // {
    //     parent::mount($record);

    //     if (! in_array($this->getRecord()->type, ['product', 'iv_product'])) {
    //         abort(404);
    //     }
    // }

    public function getTitle(): string
    {
        return 'Manage Stock for "' . $this->record->name.'"';
    }

    public static function getNavigationLabel(): string
    {
        return 'Manage Stock';
    }

    public function getRelationManagers(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(ProductResource::getUrl('index')),
        ];
    }
}
