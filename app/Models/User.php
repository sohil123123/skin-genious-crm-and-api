<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

use Laravel\Sanctum\HasApiTokens;
use App\Models\LoyaltyPointTransaction;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

// use Illuminate\Database\Eloquent\Attributes\ObservedBy;
// use App\Observers\UserObserver;

// #[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'clinic_id',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'mobile',
        'email',
        'occupation',
        'address_line_1',
        'address_line_2',
        'pincode',
        'city',
        'referral_code',
        'referred_by',
        'opt_for_loyalty',
        'how_did_you_hear',
        'total_referrals',
        'referral_earnings',
        'pending_referral_earnings',
        'loyalty_points',
        'has_diabetes',
        'has_high_bp',
        'has_cholesterol',
        'has_asthma',
        'has_heart_disease',
        'has_anaemia',
        'has_pcos',
        'has_thyroid',
        'other_diseases',
        'current_medications',
        'allergies',
        'skin_type',
        'facials_history',
        'skin_quality',
        'goal_less_tired',
        'goal_less_angry',
        'goal_less_sad',
        'goal_less_saggy',
        'goal_youthful',
        'goal_attractive',
        'goal_soft_features',
        'goal_slim_face',
        'skin_improvement',
        'email_verified_at',
        'password',
        'is_active'
    ];

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

    public function createDefaultLeaveEntitlementsIfTherapist(): void
    {
        $currentYear = now()->year;

        $defaults = [
            ['leave_type' => 'paid', 'total_allowed' => 12],
            ['leave_type' => 'unpaid', 'total_allowed' => 0],
            ['leave_type' => 'sick', 'total_allowed' => 8],
            ['leave_type' => 'emergency', 'total_allowed' => 8],
            ['leave_type' => 'other', 'total_allowed' => 0],
        ];

        foreach ($defaults as $item) {
            $exists = $this->leaveEntitlements()
                ->where('year', $currentYear)
                ->whereRaw('LOWER(leave_type) = ?', [strtolower($item['leave_type'])])
                ->exists();

            if (!$exists) {
                $this->leaveEntitlements()->create([
                    'leave_type' => strtolower($item['leave_type']),
                    'total_allowed' => $item['total_allowed'],
                    'used' => 0,
                    'remaining' => $item['total_allowed'],
                    'year' => $currentYear,
                ]);
            }
        }
    }

    public function getNameAttribute(): string
    {
        return trim(ucfirst($this->first_name) . ' ' . (ucfirst($this->last_name) ?? '')) ?: ($this->email ?? (string) $this->mobile ?? 'User');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    // -------------- Relationships ----------------------
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    // public function holidays() {
    //     return $this->hasMany(Holiday::class);
    // }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(UserLeaveEntitlement::class);
    }

    public function weeklySchedules(): HasMany
    {
        return $this->hasMany(UserWeeklySchedule::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function createdExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function approvedExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'approved_by');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(UserPackage::class);
    }

    public function holidays(): MorphMany
    {
        return $this->morphMany(AvailabilityException::class, 'exceptionable');
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    /**
     * Get the current loyalty points balance from the users table column.
     */
    public function getLoyaltyBalance(): int
    {
        return (int) $this->loyalty_points;
    }

    /**
     * Recalculate loyalty points from the ledger and sync to the users table.
     */
    public function syncLoyaltyBalance(): void
    {
        $balance = $this->loyaltyTransactions()->sum('points');
        $this->update(['loyalty_points' => $balance]);
    }

    // -------------- Custom Functions ----------------
    /**
     * Get remaining days for a leave type in the current year.
     */
    public function remainingLeaveDays(string $leaveType, ?int $year = null): int
    {
        $year = $year ?? date('Y');
        $entitlement = $this->leaveEntitlements()
            ->where('year', $year)
            ->where('leave_type', $leaveType)
            ->first();

        if (!$entitlement)
            return 0;

        return $entitlement->remaining;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }
}
