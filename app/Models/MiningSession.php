<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MiningSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_type',
        'mining_rate',
        'total_mined',
        'current_balance',
        'level',
        'experience_points',
        'streak_days',
        'multiplier',
        'last_claim_at',
        'session_started_at',
        'session_ends_at',
        'is_active',
        'achievements',
        'boosts'
    ];

    protected $casts = [
        'mining_rate' => 'decimal:8',
        'total_mined' => 'decimal:8',
        'current_balance' => 'decimal:8',
        'multiplier' => 'decimal:2',
        'last_claim_at' => 'datetime',
        'session_started_at' => 'datetime',
        'session_ends_at' => 'datetime',
        'is_active' => 'boolean',
        'achievements' => 'array',
        'boosts' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRequiredXpForNextLevel(): int
    {
        return $this->level * 100;
    }


    public function getProgressToNextLevel(): float
    {
        $requiredXp = $this->getRequiredXpForNextLevel();
        return min(($this->experience_points / $requiredXp) * 100, 100);
    }

    public function calculateDailyEarnings(): float
    {
        return ($this->mining_rate * $this->multiplier) * 86400; // 24 hours in seconds
    }

    public function isEligibleForBoost(): bool
    {
        return $this->streak_days >= 7;
    }
}
