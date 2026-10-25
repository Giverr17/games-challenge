<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
protected $fillable = ['code', 'status', 'turn_seat', 'winner_seat', 'rematch_code'];
    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    protected $casts = [
        'turn_seat' => 'integer',
        'winner_seat' => 'integer',
    ];

    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class);
    }
}
