<?php

namespace App\Filament\Resources\UserLeaveEntitlements\Pages;

use App\Filament\Resources\UserLeaveEntitlements\UserLeaveEntitlementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class ListUserLeaveEntitlements extends ListRecords
{
    protected static string $resource = UserLeaveEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modal()
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Leave Entitlement added 🎉')
                        ->body('The leave entitlement details have been successfully added.')
                ),
        ];
    }

    public function getTable(): Table
    {
        return parent::getTable()->poll('5s');
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-users')
                ->badge($this->getModel()::count())
                ->badgeColor('gray'),

            'paid' => Tab::make('Paid')
                ->icon('heroicon-m-currency-rupee')
                ->query(fn ($query) => $query->where('leave_type', 'paid'))
                ->badge($this->getModel()::where('leave_type', 'paid')->count())
                ->badgeColor('success'),

            'unpaid' => Tab::make('Unpaid')
                ->icon('heroicon-m-clock')
                ->query(fn ($query) => $query->where('leave_type', 'unpaid'))
                ->badge($this->getModel()::where('leave_type', 'unpaid')->count())
                ->badgeColor('warning'),

            'sick' => Tab::make('Sick')
                ->icon('heroicon-m-heart')
                ->query(fn ($query) => $query->where('leave_type', 'sick'))
                ->badge($this->getModel()::where('leave_type', 'sick')->count())
                ->badgeColor('danger'),

            'other' => Tab::make('Other')
                ->icon('heroicon-m-question-mark-circle')
                ->query(fn ($query) => $query->where('leave_type', 'other'))
                ->badge($this->getModel()::where('leave_type', 'other')->count())
                ->badgeColor('gray'),
        ];
    }

}
