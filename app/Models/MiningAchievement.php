<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MiningAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'icon',
        'type',
        'condition',
        'reward_amount',
        'reward_type',
        'required_value',
        'is_active'
    ];

    protected $casts = [
        'reward_amount' => 'decimal:8',
        'is_active' => 'boolean',
        'required_value' => 'integer'
    ];

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }
}
