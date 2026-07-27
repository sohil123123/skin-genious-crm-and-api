<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ClinicInventory extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('clinic_inventory');
    }
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
