<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicInventory extends Model
{
    protected $fillable = [
        'clinic_id',
        'product_id',
        'stock_quantity',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
