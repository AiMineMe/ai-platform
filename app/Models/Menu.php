<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'menu_name',
        'path',
        'components',
        'component_props',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'components' => 'array',
        'component_props' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('menu_name');
    }
}
