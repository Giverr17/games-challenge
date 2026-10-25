<?php

use function Livewire\Volt\{state};
use App\Models\Game;
use Illuminate\Support\Str;

$create = function () {
    $code = Str::random(8);
    $token = Str::random(32);

    $game = Game::create(['code' => $code, 'status' => 'waiting']);
    $game->players()->create(['seat' => 1, 'token' => $token]);

    session(["battleship.{$code}" => ['seat' => 1, 'token' => $token]]);
    return $this->redirect(route('battleship.room', $code));

};

?>

<div class="max-w-md mx-auto p-6 text-center">
    <h1 class="text-2xl font-bold mb-2">⚓ Battleship online</h1>
    <p class="text-gray-500 mb-6">Create a game, then send the link to a friend.</p>
    <button wire:click="create" class="px-5 py-3 bg-blue-600 text-white rounded-lg font-semibold">
        Create a game
    </button>
</div>