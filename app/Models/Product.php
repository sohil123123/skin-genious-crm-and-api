<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type', // product, service, iv_product
        'sku',
        'sell_price',
        'purchase_price',
        'gst',
        'unit', // ml, mg
        'stock',
        'description',
        'is_active',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }
}
