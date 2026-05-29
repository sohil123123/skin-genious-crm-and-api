<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IvSessionSnapshot extends Model
{
    protected $fillable = [
        'iv_session_id',
        'scoring_payload',
        'generation_output',
        'execution_output',
        'constraints_snapshot',
    ];

    protected $casts = [
        'scoring_payload' => 'array',
        'generation_output' => 'array',
        'execution_output' => 'array',
        'constraints_snapshot' => 'array',
    ];

    public function ivSession()
    {
        return $this->belongsTo(IvSession::class);
    }
}
