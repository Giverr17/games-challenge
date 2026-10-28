<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlayer extends Model
{
    protected $fillable = ['game_id', 'seat', 'token', 'fleet', 'ready','last_seen_at'];

    protected $casts = [
        'fleet' => 'array',
        'ready' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}