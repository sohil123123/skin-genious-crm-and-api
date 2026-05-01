<?php

use App\Enums\ExpenseCategoryType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id()->comment('Primary key for the expense category.');
            $table->string('name')->comment('Display name of the category, for example Inventory Purchase or Rent.');
            $table->string('slug')->unique()->comment('Unique machine-readable category key.');
            $table->string('type')->default(ExpenseCategoryType::Variable->value)->comment('Category cost type: fixed or variable.');
            $table->text('description')->nullable()->comment('Optional internal description for the category.');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('Ordering weight used in admin dropdowns and tables.');
            $table->tinyInteger('is_active')->default(1)->comment('Whether this category is available for new expenses. 1 = active, 0 = inactive.');
            $table->foreignId('created_by')->nullable()->comment('User who created this category.')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('Last user who updated this category.')->constrained('users')->nullOnDelete();
            $table->softDeletes()->comment('Soft delete timestamp.');
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
