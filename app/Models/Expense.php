<?php

namespace App\Models;

use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseReferenceType;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Expense extends Model
{
    use HasAuditColumns, SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('expense');
    }

    protected $fillable = [
        'clinic_id',
        'expense_category_id',
        'amount',
        'payment_method',
        'reference_type',
        'reference_id',
        'reference_number',
        'vendor_name',
        'description',
        'expense_date',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_comment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approval_status' => ExpenseApprovalStatus::class,
        'approved_at' => 'datetime',
        'payment_method' => ExpensePaymentMethod::class,
    ];

    public function scopeForCurrentClinic(Builder $query): Builder
    {
        if (! auth()->check() || auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('clinic_id', auth()->user()->clinic_id);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function referenceType(): ExpenseReferenceType
    {
        return ExpenseReferenceType::tryFrom($this->reference_type) ?? ExpenseReferenceType::Manual;
    }

    public function approvalStatus(): ExpenseApprovalStatus
    {
        return $this->approval_status instanceof ExpenseApprovalStatus
            ? $this->approval_status
            : (ExpenseApprovalStatus::tryFrom((string) $this->approval_status) ?? ExpenseApprovalStatus::Pending);
    }

    public function approve(?string $comment = null): void
    {
        $this->forceFill([
            'approval_status' => ExpenseApprovalStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_comment' => $comment,
        ])->save();
    }

    public function reject(?string $comment = null): void
    {
        $this->forceFill([
            'approval_status' => ExpenseApprovalStatus::Rejected->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_comment' => $comment,
        ])->save();
    }
}
