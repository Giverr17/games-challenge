<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;

class PruneGames extends Command
{
    protected $signature = 'games:prune {--hours=24}';

    protected $description = 'Delete Battleship games nobody has touched for a while';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));

        // players and shots go too, because their foreign keys cascade
        $count = Game::where('updated_at', '<', $cutoff)->delete();

        $this->info("Deleted {$count} old games.");

        return self::SUCCESS;
    }
}