<?php

namespace App\Models;

use App\Enums\ExpenseCategoryType;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Cviebrock\EloquentSluggable\Sluggable;

class ExpenseCategory extends Model
{
    use HasAuditColumns, SoftDeletes;
    use Sluggable;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'is_active',
        'description',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => ExpenseCategoryType::class,
        'is_active' => 'boolean',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
