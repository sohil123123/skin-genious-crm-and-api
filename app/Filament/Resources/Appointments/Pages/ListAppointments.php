<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }

    public function getTable(): Table
    {
        return parent::getTable()->poll('5s');
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        // 🔧 Helper closure for role-based scoping
        $applyRoleScope = function (Builder $query) use ($user): Builder {
            if ($user->hasRole('therapist')) {
                $query->where('therapist_id', $user->id);
            } elseif ($user->hasRole('clinic_manager')) {
                $query->where('clinic_id', $user->clinic_id);
            }
            return $query;
        };

        // 🔁 Helper function for badge counts with same scoping
        $countWithScope = function (string $status = null) use ($user, $applyRoleScope): int {
            $model = $this->getModel()::query();
            $applyRoleScope($model);

            if ($status) {
                $model->where('status', $status);
            }

            return $model->count();
        };

        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-calendar-days')
                ->query(fn (Builder $query) => $applyRoleScope($query))
                ->badge($countWithScope())
                ->badgeColor('gray'),

            'scheduled' => Tab::make('Scheduled')
                ->icon('heroicon-o-clock')
                ->query(fn (Builder $query) => $applyRoleScope($query)->where('status', 'scheduled'))
                ->badge($countWithScope('scheduled'))
                ->badgeColor('gray'),

            'confirmed' => Tab::make('Confirmed')
                ->icon('heroicon-o-check-circle')
                ->query(fn (Builder $query) => $applyRoleScope($query)->where('status', 'confirmed'))
                ->badge($countWithScope('confirmed'))
                ->badgeColor('info'),

            'in_progress' => Tab::make('In Progress')
                ->icon('heroicon-o-arrow-path')
                ->query(fn (Builder $query) => $applyRoleScope($query)->where('status', 'in_progress'))
                ->badge($countWithScope('in_progress'))
                ->badgeColor('warning'),

            'completed' => Tab::make('Completed')
                ->icon('heroicon-o-check-badge')
                ->query(fn (Builder $query) => $applyRoleScope($query)->where('status', 'completed'))
                ->badge($countWithScope('completed'))
                ->badgeColor('success'),

            'cancelled' => Tab::make('Cancelled')
                ->icon('heroicon-o-x-circle')
                ->query(fn (Builder $query) => $applyRoleScope($query)->where('status', 'cancelled'))
                ->badge($countWithScope('cancelled'))
                ->badgeColor('danger'),
        ];
    }

    // public function getDefaultActiveTab(): string | int | null
    // {
    //     return 'confirmed';
    // }
}
