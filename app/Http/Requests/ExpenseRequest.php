<?php

namespace App\Http\Requests;

use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseReferenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->user() && ! $this->user()->hasRole('super_admin')) {
            $this->merge([
                'clinic_id' => $this->user()->clinic_id,
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasRole(['super_admin', 'clinic_manager']) ?? false;
    }

    public function rules(): array
    {
        $clinicRule = $this->user()?->hasRole('super_admin')
            ? ['required', 'exists:clinics,id']
            : ['required', Rule::in([$this->user()?->clinic_id])];

        return [
            'clinic_id' => $clinicRule,
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::enum(ExpensePaymentMethod::class)],
            'reference_type' => ['required', Rule::enum(ExpenseReferenceType::class)],
            'reference_id' => ['nullable', 'integer', 'min:1'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'expense_date' => ['required', 'date'],
            'approval_status' => ['sometimes', Rule::enum(ExpenseApprovalStatus::class)],
            'approval_comment' => ['nullable', 'string'],
        ];
    }
}
