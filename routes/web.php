<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Models\Game;

Route::get('/', function () {
    return view('welcome');
});


// carry my seat from a finished game into its rematch
Route::get('/battleship/rematch/{code}', function (string $code) {
    $game = Game::where('code', $code)->firstOrFail();
    $mine = session("battleship.{$code}");

    abort_unless($mine && $game->rematch_code, 404);

    $seated = $game->players()
        ->where('seat', $mine['seat'])
        ->where('token', $mine['token'])
        ->exists();
    abort_unless($seated, 403);

    // the new game reuses the same seat and token, so my identity just moves across
    session(["battleship.{$game->rematch_code}" => $mine]);

    return redirect()->route('battleship.room', $game->rematch_code);
})->name('battleship.rematch');

// take my seat back after losing the session
Route::get('/battleship/rejoin/{code}/{token}', function (string $code, string $token) {
    $game = Game::where('code', $code)->firstOrFail();
    $player = $game->players()->where('token', $token)->firstOrFail();

    session(["battleship.{$code}" => ['seat' => $player->seat, 'token' => $player->token]]);

    return redirect()->route('battleship.room', $code);
})->middleware('throttle:20,1')->name('battleship.rejoin');

Volt::route('/minesweeper', 'minesweeper')->name('minesweeper');
Volt::route('/memory-match', 'memory-match')->name('memory-match');
Volt::route('/mastermind', 'mastermind')->name('mastermind');
Volt::route('/wordle', 'wordle')->name('wordle');
Volt::route('/battleship', 'battleship')->name('battleship');
Volt::route('/battleship/online', 'battleship-lobby')->name('battleship.lobby');
Volt::route('/battleship/play/{code}', 'battleship-room')->name('battleship.room');