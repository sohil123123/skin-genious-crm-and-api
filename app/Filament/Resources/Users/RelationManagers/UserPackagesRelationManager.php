<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\UserPackages\Tables\UserPackagesTable;
use App\Filament\Resources\UserPackages\Schemas\UserPackageForm;
use App\Filament\Resources\UserPackages\Schemas\UserPackageInfolist;
use App\Models\UserPackage;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\CreateAction;

class UserPackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'packages';

    protected static ?string $recordTitleAttribute = 'package_name';

    public function form(Schema $schema): Schema
    {
        return UserPackageForm::configure($schema);
    }

    public function infolist(Schema $schema): Schema
    {
        return UserPackageInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return UserPackagesTable::configure($table)
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label('Buy Package')
                    ->modalHeading('Buy Package')
                    ->modalWidth('7xl')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Package Purchased! 🎁')
                            ->body('The package has been successfully assigned to the client.')
                    )
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['clinic_id'] = $this->getOwnerRecord()->clinic_id;
                        $data['created_by'] = auth()->id();

                        return $data;
                    })
                    ->after(function ($record) {
                        // Decode any JSON-encoded snapshots in items
                        foreach ($record->items as $item) {
                            if (is_string($item->service_snapshot)) {
                                $item->update([
                                    'service_snapshot' => json_decode($item->service_snapshot, true),
                                ]);
                            }
                        }
                        // Recalculate package totals from items
                        $record->recalculateFromItems();
                    }),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('items.service')->latest());
    }
}
