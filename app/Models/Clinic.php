<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'start_time',
        'end_time',
        'number_of_beds',
        'is_active',
        'face_scan_machine',
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

    public function scopeActive($query)
    {
        $query->where('is_active', true);
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ucwords($value),
            set: fn ($value) => strtolower($value),
        );
    }

    public function getFullAddressAttribute()
    {
        $address = $this->address_line1;
        if ($this->address_line2) {
            $address .= ', ' . $this->address_line2;
        }
        $address .= ', ' . $this->city . ' ' . $this->pincode;
        return $address;
    }


    // --------------------- Relation -------------------
    public function manager()
    {
        return $this->hasOne(User::class)->role('clinic_manager')->whereHas('roles', fn ($q) => $q->where('name', 'clinic_manager'));;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function therapists()
    {
        return $this->users()->whereHas('roles', fn ($q) => $q->where('name', 'therapist'));
    }

    public function clients()
    {
        return $this->users()->whereHas('roles', fn ($q) => $q->where('name', 'client'));
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function holidays(): MorphMany
    {
        return $this->morphMany(AvailabilityException::class, 'exceptionable');
    }

    public function clinicInventories()
    {
        return $this->hasMany(ClinicInventory::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
