<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['clinic_id', 'first_name', 'last_name', 'gender', 'date_of_birth', 'mobile', 'email', 'occupation', 'address_line_1', 'address_line_2', 'pincode', 'city', 'referral_code', 'referred_by', 'opt_for_loyalty', 'how_did_you_hear', 'total_referrals', 'referral_earnings', 'pending_referral_earnings', 'loyalty_points', 'has_diabetes', 'has_high_bp', 'has_cholesterol', 'has_asthma',
        'has_heart_disease', 'has_anaemia', 'has_pcos', 'has_thyroid',
        'other_diseases', 'current_medications', 'allergies', 'skin_type', 'facials_history', 'skin_quality', 'goal_less_tired', 'goal_less_angry', 'goal_less_sad', 'goal_less_saggy',
        'goal_youthful', 'goal_attractive', 'goal_soft_features', 'goal_slim_face',
        'skin_improvement', 'email_verified_at', 'password', 'is_active'];

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
            'has_diabetes' => 'boolean',
            'has_high_bp' => 'boolean',
            'has_cholesterol' => 'boolean',
            'has_asthma' => 'boolean',
            'has_heart_disease' => 'boolean',
            'has_anaemia' => 'boolean',
            'has_pcos' => 'boolean',
            'has_thyroid' => 'boolean',
            'goal_less_tired' => 'boolean',
            'goal_less_angry' => 'boolean',
            'goal_less_sad' => 'boolean',
            'goal_less_saggy' => 'boolean',
            'goal_youthful' => 'boolean',
            'goal_attractive' => 'boolean',
            'goal_soft_features' => 'boolean',
            'goal_slim_face' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? '')) ?: ($this->email ?? (string) $this->mobile ?? 'User');
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function holidays() {
        return $this->hasMany(Holiday::class);
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(UserLeaveEntitlement::class);
    }

    /**
     * Get remaining days for a leave type in the current year.
     */
    public function remainingLeaveDays(string $type, int $year = null): int
    {
        $year = $year ?? date('Y');
        $entitlement = $this->leaveEntitlements()
            ->where('year', $year)
            ->where('leave_type', $type)
            ->first();

        if (!$entitlement) {
            return 0; // Or throw an exception if no entitlement set
        }

        return $entitlement->entitlement - $entitlement->taken;
    }

    /**
     * Increment taken days after approval.
     */
    public function incrementTakenLeave(string $type, int $days, int $year = null): void
    {
        $year = $year ?? date('Y');
        $entitlement = $this->leaveEntitlements()
            ->where('year', $year)
            ->where('leave_type', $type)
            ->first();

        if ($entitlement) {
            $entitlement->increment('taken', $days);
        }
    }
}
