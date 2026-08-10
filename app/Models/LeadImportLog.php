<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lifecycle audit trail for an import, shown as a relation manager on the
 * import detail screen.
 */
class LeadImportLog extends Model
{
    public const EVENT_UPLOADED = 'uploaded';
    public const EVENT_ANALYZED = 'analyzed';
    public const EVENT_MAPPED = 'mapped';
    public const EVENT_QUEUED = 'queued';
    public const EVENT_CHUNK_STARTED = 'chunk_started';
    public const EVENT_CHUNK_COMPLETED = 'chunk_completed';
    public const EVENT_DUPLICATE_ACTION = 'duplicate_action';
    public const EVENT_COMPLETED = 'completed';
    public const EVENT_FAILED = 'failed';
    public const EVENT_RETRIED = 'retried';
    public const EVENT_CANCELLED = 'cancelled';

    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR = 'error';

    protected $fillable = [
        'lead_import_id',
        'user_id',
        'level',
        'event',
        'message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LeadImport::class, 'lead_import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * @return array<string, string>
     */
    public static function eventLabels(): array
    {
        return [
            self::EVENT_UPLOADED => 'File Uploaded',
            self::EVENT_ANALYZED => 'File Analyzed',
            self::EVENT_MAPPED => 'Mapping Confirmed',
            self::EVENT_QUEUED => 'Queued',
            self::EVENT_CHUNK_STARTED => 'Chunk Started',
            self::EVENT_CHUNK_COMPLETED => 'Chunk Completed',
            self::EVENT_DUPLICATE_ACTION => 'Duplicate Handled',
            self::EVENT_COMPLETED => 'Completed',
            self::EVENT_FAILED => 'Failed',
            self::EVENT_RETRIED => 'Failed Rows Retried',
            self::EVENT_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function levelColors(): array
    {
        return [
            self::LEVEL_DEBUG => 'gray',
            self::LEVEL_INFO => 'info',
            self::LEVEL_WARNING => 'warning',
            self::LEVEL_ERROR => 'danger',
        ];
    }
}
