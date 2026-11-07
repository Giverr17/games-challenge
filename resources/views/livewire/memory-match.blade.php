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
    class="flex flex-col items-center p-6">
    <h1 class="text-2xl font-bold mb-2">🧠 Memory Match</h1>
    <p class="text-sm text-gray-500 mb-6">Moves: {{ $moves }} · Matched: {{ $matchesFound }}/{{ $pairsCount }}</p>

    <div class="flex flex-col lg:flex-row items-center lg:items-start justify-center gap-8 w-full max-w-4xl">
        {{-- Cards Grid Column --}}
        <div class="flex flex-col items-center">
            @if ($won)
                <p class="mb-2 text-lg font-bold text-green-600">🎉 You matched them all!</p>
                <button wire:click="resetGame" class="mb-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold text-sm shadow">
                    Play Again
                </button>
            @endif

            <div class="grid grid-cols-4 gap-3 p-3 bg-slate-100 rounded-2xl border border-slate-200 shadow-sm">
                @foreach ($cards as $card)
                    <div wire:key="card-{{ $card['index'] }}"
                        wire:click="!isLocked && selectCard({{ $card['index'] }})"
                        class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl flex items-center justify-center text-xl font-bold cursor-pointer select-none transition-all shadow-sm active:scale-95
                                                {{ $card['isFlipped'] || $card['isMatched'] ? 'bg-white border-2 border-blue-400 text-blue-600' : 'bg-blue-600 hover:bg-blue-500' }}">
                        @if ($card['isFlipped'] || $card['isMatched'])
                            {{ $card['value'] }}
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Side Column: How to play --}}
        <div id="how-to-play" class="w-full max-w-sm lg:w-72 flex-shrink-0">
            <div class="border border-gray-200 bg-white rounded-lg p-4 text-xs text-gray-600 space-y-3">
                <h2 class="font-semibold text-gray-900 text-sm border-b border-gray-100 pb-2">
                    How to play
                </h2>
                <div>
                    <span class="font-medium text-gray-800">Objective:</span>
                    <p class="mt-0.5 text-gray-600">Find all pairs of matching cards in as few moves as possible.</p>
                </div>
                <div>
                    <span class="font-medium text-gray-800">Rules:</span>
                    <ul class="list-disc pl-4 mt-1 space-y-1 text-gray-600">
                        <li>Click any card to flip it over.</li>
                        <li>Click a second card to see if it matches.</li>
                        <li>If they match, they stay face up. If not, they flip back over.</li>
                    </ul>
                </div>
                <div class="pt-2 border-t border-gray-100 text-gray-500">
                    <span class="font-medium text-gray-700">Tip:</span> Remember the positions of mismatched cards so you can pair them up later.
                </div>
            </div>
        </div>
    </div>
</div>