<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'original_url',
        'original_content',
        'generated_content',
        'generated_from_id',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: get the original article this was generated from
     */
    public function generatedFrom()
    {
        return $this->belongsTo(Article::class, 'generated_from_id');
    }

    /**
     * Relationship: get all generated versions of this article
     */
    public function generatedVersions()
    {
        return $this->hasMany(Article::class, 'generated_from_id');
    }

    /**
     * Scope: Get only original articles
     */
    public function scopeOriginal($query)
    {
        return $query->where('status', 'original');
    }

    /**
     * Scope: Get only generated articles
     */
    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    /**
     * Check if this article has been processed by AI
     */
    public function isGenerated(): bool
    {
        return $this->status === 'generated';
    }
};
