<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Mood extends Model
{
    protected $fillable = [
        'user_id',
        'source',
        'emotion',
        'energy',
        'valence',
        'intensity',
        'context',
        'tracks',
    ];

    protected $casts = [
        'context' => 'array',
        'tracks'  => 'array',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return match ($period) {
            'week'  => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            default => $query,
        };
    }
}
