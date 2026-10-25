<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shot extends Model
{
    protected $fillable = ['game_id', 'seat', 'cell_row', 'cell_col', 'result'];

    protected $casts = [
        'seat' => 'integer',
        'cell_row' => 'integer',
        'cell_col' => 'integer',
    ];
}