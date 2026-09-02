<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportFailureReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single row that could not be imported, kept so it can be exported, fixed
 * and replayed without needing the original file.
 */
class LeadImportFailure extends Model
{
    protected $fillable = [
        'lead_import_id',
        'row_number',
        'reason_code',
        'reason',
        'errors',
        'raw_row',
        'mapped_row',
        'is_resolved',
        'retried_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason_code' => ImportFailureReason::class,
            'errors' => 'array',
            'raw_row' => 'array',
            'mapped_row' => 'array',
            'is_resolved' => 'boolean',
            'retried_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LeadImport::class, 'lead_import_id');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Flatten the validation messages for display in a table cell.
     */
    public function getErrorSummaryAttribute(): string
    {
        if (! is_array($this->errors) || $this->errors === []) {
            return (string) $this->reason;
        }

        $parts = [];

        foreach ($this->errors as $field => $messages) {
            $parts[] = $field . ': ' . implode(', ', (array) $messages);
        }

        return implode(' · ', $parts);
    }

    public function markResolved(): void
    {
        $this->forceFill([
            'is_resolved' => true,
            'resolved_at' => now(),
        ])->save();
    }
}
