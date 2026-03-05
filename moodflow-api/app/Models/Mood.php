<?php

namespace App\Models;

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
        'tracks' => 'array',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
