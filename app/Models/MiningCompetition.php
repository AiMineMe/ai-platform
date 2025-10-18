<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MiningCompetition extends Model
{
    protected $fillable = [
        'name', 'description', 'type', 'prize_pool', 'prizes',
        'starts_at', 'ends_at', 'is_active', 'max_participants',
        'entry_fee', 'admin_fee_percentage'
    ];

    protected $casts = [
        'prize_pool' => 'decimal:8',
        'entry_fee' => 'decimal:2',
        'admin_fee_percentage' => 'decimal:2',
        'prizes' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function participants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompetitionParticipant::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && now()->between($this->starts_at, $this->ends_at);
    }

    public function canJoin(): bool
    {
        if (!$this->isActive()) return false;
        if ($this->max_participants && $this->participants()->count() >= $this->max_participants) return false;
        return true;
    }

    public function getTotalPrizePool(): float
    {
        $basePrize = (float) $this->prize_pool;
        $collectedFees = $this->getCollectedPrizeFees();
        return $basePrize + $collectedFees;
    }

    public function getCollectedPrizeFees(): float
    {
        $totalFees = $this->participants()->sum('entry_fee_paid');
        $adminCut = ($totalFees * $this->admin_fee_percentage) / 100;
        return $totalFees - $adminCut;
    }
}
