<?php

use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseReferenceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id()->comment('Primary key for the expense entry.');
            $table->foreignId('clinic_id')->comment('Clinic that owns this expense.')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('expense_category_id')->comment('Normalized category for reporting and filtering.')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('amount', 12, 2)->comment('Expense amount in INR.');
            $table->string('payment_method')->default(ExpensePaymentMethod::Cash->value)->comment('Payment method used: cash, card, UPI, bank transfer, cheque, wallet, or other.');
            $table->string('reference_type')->default(ExpenseReferenceType::Manual->value)->comment('Source module for this expense: manual, purchase, salary, rent, utility, or misc.');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('Nullable source record ID for polymorphic references.');
            $table->string('reference_number')->nullable()->comment('External bill, invoice, receipt, or transaction number.');
            $table->string('vendor_name')->nullable()->comment('Vendor, supplier, employee, or payee name.');
            $table->text('description')->nullable()->comment('Optional expense description.');
            $table->date('expense_date')->comment('Business date on which the expense occurred.');
            $table->string('approval_status')->default(ExpenseApprovalStatus::Pending->value)->comment('Admin approval status: pending, approved, or rejected.');
            $table->foreignId('approved_by')->nullable()->comment('Admin user who approved or rejected this expense.')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->comment('Timestamp when the expense was approved or rejected.');
            $table->text('approval_comment')->nullable()->comment('Optional admin comment captured during approval or rejection.');
            $table->foreignId('created_by')->nullable()->comment('User who created this expense.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('Last user who updated this expense.')->constrained('users')->nullOnDelete();
            $table->softDeletes()->comment('Soft delete timestamp.');
            $table->timestamps();

            $table->index(['clinic_id', 'expense_date']);
            $table->index(['expense_category_id', 'expense_date']);
            $table->index(['payment_method', 'expense_date']);
            $table->index(['approval_status', 'expense_date']);
            $table->index('approved_by');
            $table->index(['reference_type', 'reference_id']);
            $table->unique(['reference_type', 'reference_id'], 'expenses_unique_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
