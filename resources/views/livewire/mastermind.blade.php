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
    <p class="text-sm text-gray-500 mb-4">Attempts: {{ count($history) }} / {{ $maxAttempts }}</p>

    @if ($gameOver)
        <p class="mb-2 text-lg font-bold {{ $won ? 'text-green-600' : 'text-red-600' }}">
            {{ $won ? '🎉 You cracked it!' : '💥 Out of attempts!' }}
        </p>
        <div class="flex gap-2 mb-3">
            @foreach ($secret as $color)
                <div class="w-9 h-9 rounded-full {{ $colorClasses[$color] }}"></div>
            @endforeach
        </div>
    @endif

    {{-- past guesses with their pegs --}}
    <div class="flex flex-col gap-2 mb-4">
        @foreach ($history as $row)
            <div class="flex items-center gap-3">
                <div class="flex gap-2">
                    @foreach ($row['guess'] as $color)
                        <div class="w-9 h-9 rounded-full {{ $colorClasses[$color] }}"></div>
                    @endforeach
                </div>
                <span class="text-sm font-mono">⚫ {{ $row['black'] }} · ⚪ {{ $row['white'] }}</span>
            </div>
        @endforeach
    </div>

    {{-- the guess being built --}}
    @if (! $gameOver)
        <div class="flex gap-2 mb-4">
            @for ($i = 0; $i < $codeLength; $i++)
                <div class="w-9 h-9 rounded-full border-2 border-gray-400
                    {{ isset($currentGuess[$i]) ? $colorClasses[$currentGuess[$i]] : 'bg-gray-100' }}"></div>
            @endfor
        </div>

        <div class="flex gap-2 mb-4">
            @foreach ($colors as $color)
                <button wire:click="addColor('{{ $color }}')"
                    class="w-10 h-10 rounded-full {{ $colorClasses[$color] }} active:scale-95 transition"></button>
            @endforeach
        </div>

        <div class="flex gap-2">
            <button wire:click="removeLast" class="px-4 py-2 bg-gray-200 rounded">Undo</button>
            <button wire:click="submitGuess" class="px-4 py-2 bg-blue-600 text-white rounded">Submit</button>
        </div>
    @else
        <button wire:click="newGame" class="px-4 py-2 bg-blue-600 text-white rounded">Play Again</button>
    @endif
</div>