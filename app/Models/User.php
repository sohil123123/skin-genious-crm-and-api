<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['first_name', 'last_name', 'gender', 'date_of_birth', 'mobile', 'email', 'occupation', 'address_line_1', 'address_line_2', 'pincode', 'city', 'referral_code', 'referred_by', 'opt_for_loyalty', 'how_did_you_hear', 'total_referrals', 'referral_earnings', 'pending_referral_earnings', 'email_verified_at', 'password', 'is_active'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'opt_for_loyalty' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? '')) ?: ($this->email ?? (string) $this->mobile ?? 'User');
    }
}
