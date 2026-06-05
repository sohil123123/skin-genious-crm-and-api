<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessageLog extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_message_logs';

    protected $fillable = [
        'user_id',
        'phone_number',
        'template_name',
        'message_id',
        'status',
        'error_response',
        'sent_at',
    ];

    protected $casts = [
        'error_response' => 'array',
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
