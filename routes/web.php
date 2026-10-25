<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
});

Volt::route('/minesweeper', 'minesweeper')->name('minesweeper');
Volt::route('/memory-match', 'memory-match')->name('memory-match');
Volt::route('/mastermind', 'mastermind')->name('mastermind');
Volt::route('/wordle', 'wordle')->name('wordle');
Volt::route('/battleship', 'battleship')->name('battleship');
Volt::route('/hello', 'hello')->name('hello');
Volt::route('/battleship/online', 'battleship-lobby')->name('battleship.lobby');
Volt::route('/battleship/play/{code}', 'battleship-room')->name('battleship.room');