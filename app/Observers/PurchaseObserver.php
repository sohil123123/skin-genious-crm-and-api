<?php

namespace App\Observers;

use App\Enums\ExpenseCategoryType;
use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseReferenceType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Purchase;

class PurchaseObserver
{
    public function created(Purchase $purchase): void
    {
        $this->syncExpense($purchase);
    }

    public function updated(Purchase $purchase): void
    {
        if ($purchase->wasChanged(['total_amount', 'payment_mode', 'purchase_date', 'clinic_id', 'supplier_name', 'notes'])) {
            $this->syncExpense($purchase);
        }
    }

    private function syncExpense(Purchase $purchase): void
    {
        if ((float) $purchase->total_amount <= 0) {
            return;
        }

        $category = ExpenseCategory::firstOrCreate(
            ['slug' => 'inventory-purchase'],
            [
                'name' => 'Inventory Purchase',
                'type' => ExpenseCategoryType::Variable->value,
                'is_active' => true,
                'description' => 'Automatically generated from purchase entries.',
                'sort_order' => 10,
            ],
        );

        $expense = Expense::withTrashed()->firstOrNew([
            'reference_type' => ExpenseReferenceType::Purchase->value,
            'reference_id' => $purchase->id,
        ]);

        $expense->fill([
            'clinic_id' => $purchase->clinic_id,
            'expense_category_id' => $category->id,
            'amount' => $purchase->total_amount,
            'payment_method' => ExpensePaymentMethod::tryFrom((string) $purchase->payment_mode)?->value
                ?? ExpensePaymentMethod::Other->value,
            'reference_number' => $purchase->notes,
            'vendor_name' => $purchase->supplier_name,
            'description' => "Inventory purchase #{$purchase->id}",
            'expense_date' => $purchase->purchase_date,
            'approval_status' => ExpenseApprovalStatus::Pending->value,
            'created_by' => $expense->exists ? $expense->created_by : $purchase->created_by,
            'updated_by' => $purchase->updated_by,
        ]);

        if ($expense->trashed()) {
            $expense->restore();
        }

        $expense->save();
    }
}
