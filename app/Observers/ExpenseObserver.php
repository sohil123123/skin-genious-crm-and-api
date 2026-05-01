<?php

namespace App\Observers;

use App\Models\Expense;
use App\Models\User;
use Filament\Notifications\Notification;

class ExpenseObserver
{
    public function created(Expense $expense): void
    {
        $creator = $expense->creator;

        if ($creator?->hasRole('super_admin')) {
            return;
        }

        $admins = User::role('super_admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('New expense pending approval')
            ->body(sprintf(
                '%s submitted an expense of Rs. %s for %s.',
                $creator?->name ?? 'A user',
                number_format((float) $expense->amount, 2),
                $expense->clinic?->name ?? 'a clinic',
            ))
            ->icon('heroicon-o-banknotes')
            ->warning()
            ->sendToDatabase($admins);
    }
}
