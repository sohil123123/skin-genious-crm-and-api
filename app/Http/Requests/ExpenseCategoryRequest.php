<?php

namespace App\Http\Requests;

use App\Enums\ExpenseCategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['super_admin', 'clinic_manager']) ?? false;
    }

    public function rules(): array
    {
        $categoryId = $this->route('expense_category')?->id ?? $this->route('expenseCategory')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'name')->ignore($categoryId)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('expense_categories', 'slug')->ignore($categoryId)],
            'type' => ['required', Rule::enum(ExpenseCategoryType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
