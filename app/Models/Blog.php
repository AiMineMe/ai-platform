<?php

namespace App\Models;

use App\Concerns\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasFactory, UploadedFile;

    protected $fillable = [
        'title',
        'excerpt',
        'content',
        'slug',
        'image',
        'icon',
        'color',
        'read_time',
        'is_published'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];


    public function getImageAttribute(): ?string
    {
        $imageValue = $this->attributes['image'] ?? null;
        return $imageValue ? $this->fullPath($imageValue) : null;
    }

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
