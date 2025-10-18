<?php

namespace App\Models;

use App\Enums\Wallet\Type;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'uid',
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'status',
        'kyc_status',
        'last_login_at',
        'avatar',
        'is_admin',
        'remember_token',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
        'referral_code',
        'referred_by',
        'phone',
        'subscription_plan_id',
        'subscription_expires_at',
    ];


    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($user) {
            $user->uid = Str::random(16);
            $user->referral_code = Str::random(10);
        });
    }

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_recovery_codes' => 'json',
        'last_login_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
    ];

    protected $appends = ['avatar_url'];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('assets/files/'.$this->avatar) : null;
    }


    public function hasTwoFactorEnabled(): bool
    {
        return !is_null($this->two_factor_confirmed_at);
    }

    public function mainWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', Type::MAIN->value);
    }

    public function tradeWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', Type::TRADE->value);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class, 'email', 'email');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referredUsers(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function kycVerification(): HasOne
    {
        return $this->hasOne(KycVerification::class);
    }

    public function hasKycVerification(): bool
    {
        return $this->kycVerification !== null;
    }

    public function isKycApproved(): bool
    {
        return $this->kycVerification && $this->kycVerification->isApproved();
    }

    public function kycStatus(): string
    {
        return $this->kycVerification ? $this->kycVerification->status : 'not_submitted';
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function subscriptionPlan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }


    public function hasActiveSubscription(): bool
    {
        return $this->subscription_plan_id &&
            $this->subscription_expires_at &&
            $this->subscription_expires_at->isFuture();
    }

    public function getMiningFeeRate(): float
    {
        if (!$this->hasActiveSubscription()) {
            return Setting::get('mining_fee', 10);
        }

        return $this->subscriptionPlan->mining_fee_percentage;
    }

    public function getMiningMultiplier(): float
    {
        if (!$this->hasActiveSubscription()) {
            return Setting::get('mining_rate_multiplier', 1);
        }

        return $this->subscriptionPlan->mining_rate_multiplier;
    }

    public function getCurrentPlanName(): string
    {
        if (!$this->hasActiveSubscription()) {
            return 'Free';
        }

        return $this->subscriptionPlan->name;
    }
}
