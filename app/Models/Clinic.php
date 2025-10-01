<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clinic extends Model
{
    use HasFactory, SoftDeletes;
    use Sluggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'manager_id',
        'slug',
        'name',
        'address_line1',
        'address_line2',
        'pincode',
        'city',
        'gst_number',
        'first_sale_share',
        'sale_share',
        'google_map_link',
        'logo',
        'phone',
        'email',
        'website',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'first_sale_share' => 'decimal:2',
        'sale_share' => 'decimal:2',
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

    /**
     * Get the manager of the clinic.
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get the users assigned to the clinic.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function managers()
    {
        return $this->users()->whereHas('roles', fn ($q) => $q->where('name', 'clinic_manager'));
    }

    /**
     * Scope a query to only include active clinics.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->where('is_active', true);
    }

    /**
     * Get the full address as a string.
     *
     * @return string
     */
    public function getFullAddressAttribute()
    {
        $address = $this->address_line1;
        if ($this->address_line2) {
            $address .= ', ' . $this->address_line2;
        }
        $address .= ', ' . $this->city . ' ' . $this->pincode;
        return $address;
    }
}
