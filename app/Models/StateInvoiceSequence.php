<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StateInvoiceSequence extends Model
{
    protected $fillable = [
        'state_code',
        'last_number',
    ];
}
