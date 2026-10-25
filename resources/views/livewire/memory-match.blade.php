<?php
use function Livewire\Volt\{state, mount};

state([
    'pairsCount' => 8,       // 8 pairs = 16 cards
    'cards' => [],
    'flippedIndexes' => [],  // holds up to 2 indexes currently face-up, unresolved
    'matchesFound' => 0,
    'moves' => 0,
    'won' => false,
    // 'locked' is now handled client-side by Alpine (isLocked)
]);

mount(function () {
    $values = [];
    for ($i = 0; $i < $this->pairsCount; $i++) {
        $values[] = $i;
        $values[] = $i;
    }

    //values[0,0,1,1,2,2,3,3,4,4]; e.t.c
    $cards = [];
    foreach ($values as $index => $value) {
        $cards[$index] = [
            'index' => $index,
            'value' => $value,
            'isFlipped' => false,
            'isMatched' => false,
        ];
    }

    //Fisher-Yates shuffle
    for ($i = count($cards) - 1; $i >= 0; $i--) {
        $j = rand(0, $i);
        [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
    }

    // Re-index so each card's 'index' matches its actual position in the array
    // (shuffle moves cards around, making the original index stale)
    $cards = array_values($cards);
    foreach ($cards as $pos => $card) {
        $cards[$pos]['index'] = $pos;
    }

    $this->cards = $cards;
});

$selectCard = function ($index) {
    $card = $this->cards[$index];
    if ($card['isFlipped'] || $card['isMatched']) {
        return;
    }

    $this->cards[$index]['isFlipped'] = true;
    $this->flippedIndexes[] = $index;

    if (count($this->flippedIndexes) < 2) {
        return;
    }
    $this->moves++;

    [$first, $second] = $this->flippedIndexes;

    if ($this->cards[$first]['value'] === $this->cards[$second]['value']) {
        $this->cards[$first]['isMatched'] = true;
        $this->cards[$second]['isMatched'] = true;
        $this->matchesFound++;
        $this->flippedIndexes = [];

        if ($this->matchesFound === $this->pairsCount) {
            $this->won = true;
        }
    } else {
        $this->dispatch('mismatch');
    }

};

$resetGame = function () {
    $this->reset(['cards', 'flippedIndexes', 'matchesFound', 'moves', 'won',]);

    // rebuild + reshuffle, same logic as mount()
    $values = [];
    for ($i = 0; $i < $this->pairsCount; $i++) {
        $values[] = $i;
        $values[] = $i;
    }

    $cards = [];
    foreach ($values as $index => $value) {
        $cards[$index] = [
            'index' => $index,
            'value' => $value,
            'isFlipped' => false,
            'isMatched' => false,
        ];
    }

    for ($i = count($cards) - 1; $i > 0; $i--) {
        $j = rand(0, $i);
        [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
    }

    $cards = array_values($cards);
    foreach ($cards as $pos => $card) {
        $cards[$pos]['index'] = $pos;
    }

    $this->cards = $cards;
};

$resetFlipped = function () {
    foreach ($this->flippedIndexes as $index) {
        $this->cards[$index]['isFlipped'] = false;
    }
    $this->flippedIndexes = [];
    $this->locked = false;
};
?>

<div x-data="{ isLocked: false }"
    x-on:mismatch.window="isLocked = true; setTimeout(() => { $wire.resetFlipped(); isLocked = false; }, 500)"
    class="flex flex-col items-center p-8">
    <h1 class="text-2xl font-bold mb-2">🧠 Memory Match</h1>
    <p class="text-sm text-gray-500 mb-4">Moves: {{ $moves }} · Matched: {{ $matchesFound }}/{{ $pairsCount }}</p>
    @if ($won)
        <p class="mb-2 text-lg font-bold text-green-600">🎉 You matched them all!</p>
        <button wire:click="resetGame" class="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Play Again
        </button>
    @endif

    <div class="grid grid-cols-4 gap-3">
        @foreach ($cards as $card)
            <div wire:click="!isLocked && selectCard({{ $card['index'] }})"
                class="w-16 h-16 sm:w-20 sm:h-20 rounded-lg flex items-center justify-center text-xl font-bold cursor-pointer select-none transition
                                        {{ $card['isFlipped'] || $card['isMatched'] ? 'bg-white border-2 border-blue-400' : 'bg-blue-600' }}">
                @if ($card['isFlipped'] || $card['isMatched'])
                    {{ $card['value'] }}
                @endif
            </div>
        @endforeach
    </div>
</div>