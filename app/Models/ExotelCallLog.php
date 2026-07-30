<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExotelCallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'CallSid',
        'CallFrom',
        'DialWhomNumber',
        'Direction',
        'Created',
    ];
}
