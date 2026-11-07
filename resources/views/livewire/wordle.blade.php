<?php
// resources/views/livewire/wordle.blade.php

use function Livewire\Volt\{state, mount};

state([
    'wordLength' => 5,
    'maxAttempts' => 6,
    'secret' => '',
    'currentGuess' => '',
    'history' => [],      // each row: ['guess' => 'paper', 'result' => ['yellow', ...]]
    'message' => '',
    'gameOver' => false,
    'won' => false,
]);

mount(function () {
    $this->newGame();
});

// the word list lives in a method so it is not sent to the browser as state
$wordList = function () {
    return [
        'apple',
        'paper',
        'eerie',
        'crane',
        'slate',
        'brick',
        'chair',
        'dream',
        'eagle',
        'flame',
        'grape',
        'house',
        'input',
        'jelly',
        'knife',
        'lemon',
        'mango',
        'night',
        'ocean',
        'piano',
        'queen',
        'river',
        'stone',
        'table',
        'uncle',
        'vivid',
        'water',
        'young',
        'zebra',
        'bread',
        'cloud',
        'dance',
        'earth',
        'field',
        'ghost',
        'heart',
        'image',
        'juice',
        'light',
        'money',
        'nurse',
        'olive',
        'plant',
        'quiet',
        'round',
        'smile',
        'tiger',
        'urban',
        'voice',
        'world',
        'yacht',
        'begin',
        'candy',
        'daily',
        'empty',
        'faith',
        'giant',
        'happy',
        'ivory',
        'joker',
    ];
};

$newGame = function () {
    $words = $this->wordList();
    $this->secret = $words[rand(0, count($words) - 1)];
    $this->currentGuess = '';
    $this->history = [];
    $this->message = '';
    $this->gameOver = false;
    $this->won = false;
};

$scoreGuess = function ($secret, $guess) {
    $secretLetters = str_split($secret);
    $guessLetters = str_split($guess);
    $result = array_fill(0, $this->wordLength, 'gray');
    $pool = [];

    for ($i = 0; $i < $this->wordLength; $i++) {
        if ($secretLetters[$i] === $guessLetters[$i]) {
            $result[$i] = 'green';
        } else {
            $pool[] = $secretLetters[$i];
        }
    }

    for ($i = 0; $i < $this->wordLength; $i++) {
        if ($result[$i] === 'green') {
            continue;
        }
        $pos = array_search($guessLetters[$i], $pool);
        if ($pos !== false) {
            $result[$i] = 'yellow';
            unset($pool[$pos]);
        }
    }
    return $result;

};

$addLetter = function ($letter) {
    if ($this->gameOver || strlen($this->currentGuess) >= $this->wordLength) {
        return;
    }
    if (!preg_match('/^[a-z]$/', $letter)) {
        return;
    }
    $this->message = '';
    $this->currentGuess .= $letter;
};

$removeLetter = function () {
    if ($this->gameOver) {
        return;
    }
    $this->currentGuess = substr($this->currentGuess, 0, -1);
};

$submitGuess = function () {
    if ($this->gameOver) {
        return;
    }

    if (strlen($this->currentGuess) !== $this->wordLength) {
        $this->message = 'Not enough letters';
        return;
    }

    $validWords = array_flip($this->wordList());
    if (!isset($validWords[$this->currentGuess])) {
        $this->message = 'Not in word list';
        return;
    }

    $result = $this->scoreGuess($this->secret, $this->currentGuess);

    $this->history[] = [
        'guess' => $this->currentGuess,
        'result' => $result,
    ];

    $this->currentGuess = '';

    if (!in_array('gray', $result) && !in_array('yellow', $result)) {
        $this->won = true;
        $this->gameOver = true;
        $this->message = 'You got it!';
    } elseif (count($this->history) >= $this->maxAttempts) {
        $this->gameOver = true;
        $this->message = 'The word was ' . strtoupper($this->secret);
    }
};

?>


@php
    // derived data: keyboard colors are worked out from history every render, never stored
    $rank = ['gray' => 1, 'yellow' => 2, 'green' => 3];
    $keyStatus = [];
    foreach ($history as $row) {
        foreach (str_split($row['guess']) as $i => $letter) {
            $status = $row['result'][$i];
            if (! isset($keyStatus[$letter]) || $rank[$status] > $rank[$keyStatus[$letter]]) {
                $keyStatus[$letter] = $status;
            }
        }
    }

    $tileClasses = [
        'green' => 'bg-green-600 text-white border-green-600',
        'yellow' => 'bg-yellow-500 text-white border-yellow-500',
        'gray' => 'bg-gray-500 text-white border-gray-500',
    ];

    $keyboardRows = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm'];
@endphp

<div
    x-data
    x-on:keydown.window="
        if ($event.ctrlKey || $event.metaKey || $event.altKey) return;
        if ($event.key === 'Enter') $wire.submitGuess();
        else if ($event.key === 'Backspace') $wire.removeLetter();
        else if (/^[a-zA-Z]$/.test($event.key)) $wire.addLetter($event.key.toLowerCase());
    "
    class="flex flex-col items-center p-4 sm:p-6"
>
    <h1 class="text-2xl font-bold mb-1">🔤 Wordle</h1>
    <p class="text-sm text-gray-500 mb-3">Attempt {{ min(count($history) + ($gameOver ? 0 : 1), $maxAttempts) }} / {{ $maxAttempts }}</p>

    <p class="h-6 mb-2 font-semibold {{ $won ? 'text-green-600' : 'text-gray-700' }}">{{ $message }}</p>

    <div class="flex flex-col lg:flex-row items-center lg:items-start justify-center gap-8 w-full max-w-4xl">
        {{-- Wordle Game Column --}}
        <div class="flex flex-col items-center w-full max-w-md">
            {{-- the grid --}}
            <div class="flex flex-col gap-1.5 mb-4">
                @for ($r = 0; $r < $maxAttempts; $r++)
                    <div class="flex gap-1.5">
                        @for ($c = 0; $c < $wordLength; $c++)
                            @php
                                if ($r < count($history)) {
                                    $letter = $history[$r]['guess'][$c];
                                    $cls = $tileClasses[$history[$r]['result'][$c]];
                                } elseif ($r === count($history) && ! $gameOver) {
                                    $letter = $currentGuess[$c] ?? '';
                                    $cls = 'border-gray-400 bg-white';
                                } else {
                                    $letter = '';
                                    $cls = 'border-gray-300 bg-white';
                                }
                            @endphp
                            <div class="w-12 h-12 sm:w-14 sm:h-14 border-2 rounded-lg flex items-center justify-center text-xl font-bold uppercase shadow-xs {{ $cls }}">{{ $letter }}</div>
                        @endfor
                    </div>
                @endfor
            </div>

            {{-- on-screen keyboard --}}
            <div class="flex flex-col gap-1.5 w-full">
                @foreach ($keyboardRows as $rowIndex => $keys)
                    <div class="flex gap-1 justify-center">
                        @if ($rowIndex === 2)
                            <button wire:click="submitGuess" class="flex-[1.5] h-12 rounded-lg bg-gray-300 hover:bg-gray-400 active:scale-95 text-xs font-bold transition">ENTER</button>
                        @endif
                        @foreach (str_split($keys) as $key)
                            <button
                                wire:click="addLetter('{{ $key }}')"
                                class="flex-1 min-w-0 h-12 rounded-lg text-sm font-bold uppercase active:scale-95 transition shadow-2xs
                                    {{ isset($keyStatus[$key]) ? $tileClasses[$keyStatus[$key]] : 'bg-gray-200 hover:bg-gray-300' }}"
                            >{{ $key }}</button>
                        @endforeach
                        @if ($rowIndex === 2)
                            <button wire:click="removeLetter" class="flex-[1.5] h-12 rounded-lg bg-gray-300 hover:bg-gray-400 active:scale-95 text-sm font-bold transition">⌫</button>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($gameOver)
                <button wire:click="newGame" class="mt-4 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-semibold text-sm rounded-lg transition shadow">Play Again</button>
            @endif
        </div>

        {{-- Side Column: How to play --}}
        <div id="how-to-play" class="w-full max-w-sm lg:w-72 flex-shrink-0">
            <div class="border border-gray-200 bg-white rounded-lg p-4 text-xs text-gray-600 space-y-3">
                <h2 class="font-semibold text-gray-900 text-sm border-b border-gray-100 pb-2">
                    How to play
                </h2>
                <div>
                    <span class="font-medium text-gray-800">Objective:</span>
                    <p class="mt-0.5 text-gray-600">Guess the 5-letter hidden word in 6 attempts or fewer.</p>
                </div>
                <div>
                    <span class="font-medium text-gray-800">Color Clues:</span>
                    <ul class="list-disc pl-4 mt-1 space-y-1 text-gray-600">
                        <li><b>Green:</b> Correct letter in the correct position.</li>
                        <li><b>Yellow:</b> Correct letter, but in the wrong position.</li>
                        <li><b>Gray:</b> Letter is not in the word.</li>
                    </ul>
                </div>
                <div class="pt-2 border-t border-gray-100 text-gray-500">
                    <span class="font-medium text-gray-700">Tip:</span> Starting with vowel-heavy words like <i>CRANE</i> or <i>SLATE</i> helps reveal key letters quickly.
                </div>
            </div>
        </div>
    </div>
</div>