<?php
use function Livewire\Volt\{state, mount};

state([
    'colors' => ['red', 'blue', 'green', 'yellow', 'purple', 'orange'],
    'codeLength' => 4,
    'maxAttempts' => 10,
    'secret' => [],
    'currentGuess' => [],
    'history' => [],
    'gameOver' => false,
    'won' => false,
]);

mount(function () {
    $this->newGame();
});

$newGame = function () {
    $secret = [];
    for ($i = 0; $i < $this->codeLength; $i++) {
        $secret[] = $this->colors[rand(0, count($this->colors) - 1)];
    }

    $this->secret = $secret;
    $this->currentGuess = [];
    $this->history = [];
    $this->gameOver = false;
    $this->won = false;
};

$scoreGuess = function ($secret, $guess) {
    $black = 0;
    $white = 0;
    $secretLeft = [];
    $guessLeft = [];

    for ($i = 0; $i < $this->codeLength; $i++) {
        if ($secret[$i] === $guess[$i]) {
            $black++;
        } else {
            $secretLeft[] = $secret[$i];
            $guessLeft[] = $guess[$i];
        }
    }

    foreach ($guessLeft as $color) {    
        $pos = array_search($color, $secretLeft);
        if ($pos !== false) {
            $white++;
            unset($secretLeft[$pos]);
        }
    }

    return ['black' => $black, 'white' => $white];

};

$addColor = function ($color) {
    if ($this->gameOver || count($this->currentGuess) >= $this->codeLength) {
        return;
    }
    $this->currentGuess[] = $color;
};

$removeLast = function () {
    $guess = $this->currentGuess;
    array_pop($guess);
    $this->currentGuess = $guess;
};

$submitGuess = function () {
    if ($this->gameOver || count($this->currentGuess) !== $this->codeLength) {
        return;
    }

    $score = $this->scoreGuess($this->secret, $this->currentGuess);
    $this->history[] = [
        'guess' => $this->currentGuess,
        'black' => $score['black'],
        'white' => $score['white'],
    ];

    $this->currentGuess = [];

    if($score['black'] === $this->codeLength){
        $this->won = true;
        $this->gameOver = true;
    }elseif(count($this->history) >= $this->maxAttempts){
        $this->gameOver = true;
    }
};

?>

@php
    $colorClasses = [
        'red' => 'bg-red-500',
        'blue' => 'bg-blue-500',
        'green' => 'bg-green-500',
        'yellow' => 'bg-yellow-400',
        'purple' => 'bg-purple-500',
        'orange' => 'bg-orange-500',
    ];
@endphp

<div class="flex flex-col items-center p-6">
    <h1 class="text-2xl font-bold mb-1">🎯 Mastermind</h1>
    <p class="text-sm text-gray-500 mb-6">Attempts: {{ count($history) }} / {{ $maxAttempts }}</p>

    <div class="flex flex-col lg:flex-row items-center lg:items-start justify-center gap-8 w-full max-w-4xl">
        {{-- Game Board Column --}}
        <div class="flex flex-col items-center w-full max-w-sm">
            @if ($gameOver)
                <p class="mb-2 text-lg font-bold {{ $won ? 'text-green-600' : 'text-red-600' }}">
                    {{ $won ? '🎉 You cracked it!' : '💥 Out of attempts!' }}
                </p>
                <div class="flex gap-2 mb-4 p-2 bg-slate-100 rounded-xl border border-slate-200">
                    @foreach ($secret as $color)
                        <div class="w-9 h-9 rounded-full {{ $colorClasses[$color] }} shadow-sm"></div>
                    @endforeach
                </div>
            @endif

            {{-- past guesses with their pegs --}}
            <div class="flex flex-col gap-2 mb-4 w-full">
                @foreach ($history as $row)
                    <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-200 rounded-xl shadow-xs">
                        <div class="flex gap-2">
                            @foreach ($row['guess'] as $color)
                                <div class="w-8 h-8 rounded-full {{ $colorClasses[$color] }} shadow-xs"></div>
                            @endforeach
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-700 bg-white px-2 py-1 rounded border border-slate-200">
                            ⚫ {{ $row['black'] }} · ⚪ {{ $row['white'] }}
                        </span>
                    </div>
                @endforeach
            </div>

            {{-- the guess being built --}}
            @if (! $gameOver)
                <div class="flex gap-2 mb-4 p-2 bg-slate-100 rounded-xl border border-slate-200">
                    @for ($i = 0; $i < $codeLength; $i++)
                        <div class="w-9 h-9 rounded-full border-2 border-slate-400
                            {{ isset($currentGuess[$i]) ? $colorClasses[$currentGuess[$i]] : 'bg-white' }} shadow-xs"></div>
                    @endfor
                </div>

                <div class="flex gap-2.5 mb-4">
                    @foreach ($colors as $color)
                        <button wire:click="addColor('{{ $color }}')"
                            class="w-10 h-10 rounded-full {{ $colorClasses[$color] }} active:scale-95 transition-transform shadow hover:brightness-110"></button>
                    @endforeach
                </div>

                <div class="flex gap-2">
                    <button wire:click="removeLast" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 active:scale-95 rounded-lg text-sm font-semibold transition">
                        Undo
                    </button>
                    <button wire:click="submitGuess" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white rounded-lg text-sm font-semibold transition shadow">
                        Submit
                    </button>
                </div>
            @else
                <button wire:click="newGame" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white rounded-lg text-sm font-semibold transition shadow">
                    Play Again
                </button>
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
                    <p class="mt-0.5 text-gray-600">Deduce the secret 4-color combination in 10 or fewer attempts.</p>
                </div>
                <div>
                    <span class="font-medium text-gray-800">Clue Pins:</span>
                    <ul class="list-disc pl-4 mt-1 space-y-1 text-gray-600">
                        <li><b>⚫ Black:</b> A correct color in the correct position.</li>
                        <li><b>⚪ White:</b> A correct color, but in the wrong position.</li>
                    </ul>
                </div>
                <div class="pt-2 border-t border-gray-100 text-gray-500">
                    <span class="font-medium text-gray-700">Tip:</span> Eliminate unrewarded colors early and keep track of successful positions.
                </div>
            </div>
        </div>
    </div>
</div>