<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'mining_competition_id',
        'user_id',
        'mined_amount',
        'rank',
        'prize_won',
        'prize_claimed',
        'entry_fee_paid',
        'joined_at'
    ];

    protected $casts = [
        'mined_amount' => 'decimal:8',
        'prize_won' => 'decimal:8',
        'prize_claimed' => 'boolean',
        'joined_at' => 'timestamp',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(MiningCompetition::class, 'mining_competition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
