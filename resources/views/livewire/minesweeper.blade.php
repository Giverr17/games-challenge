<?php

use function Livewire\Volt\{state, mount};

state([
    'rows' => 9,
    'cols' => 9,
    'mineCount' => 10,
    'grid' => [],
    'minesPlaced' => false,
    'gameOver' => false,
    'won' => false,
    'elapsedSeconds' => 0,
]);

mount(function () {
    $grid = [];
    for ($row = 0; $row < $this->rows; $row++) {
        for ($col = 0; $col < $this->cols; $col++) {
            $grid[$row][$col] = [
                'row' => $row,
                'col' => $col,
                'isMine' => false,
                'isRevealed' => false,
                'isFlagged' => false,
                'adjacentMines' => 0,
            ];
        }
    }
    $this->grid = $grid;
});

$getNeighbours = function ($row, $col) {
    $neighbours = [];
    for ($r = $row - 1; $r <= $row + 1; $r++) {
        for ($c = $col - 1; $c <= $col + 1; $c++) {
            if ($r === $row && $c === $col) {
                continue;
            }
            if ($r < 0 || $r >= $this->rows || $c < 0 || $c >= $this->cols) {
                continue;
            }
            $neighbours[] = [$r, $c];
        }

    }
    return $neighbours;
};

$placeMines = function ($excludeRow, $excludeCol) {
    $protected = collect($this->getNeighbours($excludeRow, $excludeCol))
        ->push([$excludeRow, $excludeCol])
        ->map(fn($pos) => "{$pos[0]}-{$pos[1]}")
        ->toArray();

    $placed = 0;
    while ($placed < $this->mineCount) {
        $r = rand(0, $this->rows - 1);
        $c = rand(0, $this->cols - 1);
        $key = "{$r}-{$c}";

        if (in_array($key, $protected) || $this->grid[$r][$c]['isMine']) {
            continue;
        }
        $this->grid[$r][$c]['isMine'] = true;
        $placed++;
    }

    for ($row = 0; $row < $this->rows; $row++) {
        for ($col = 0; $col < $this->cols; $col++) {
            if ($this->grid[$row][$col]['isMine']) {
                continue;
            }
            $count = 0;
            foreach ($this->getNeighbours($row, $col) as [$nr, $nc]) {
                if ($this->grid[$nr][$nc]['isMine']) {
                    $count++;
                }
            }
            $this->grid[$row][$col]['adjacentMines'] = $count;
        }
    }
    $this->minesPlaced = true;

};

$tick = function () {
    if ($this->minesPlaced && ! $this->gameOver) {
        $this->elapsedSeconds++;
    }
};



$checkWin = function () {
    foreach ($this->grid as $row) {
        foreach ($row as $cell) {
            // if any non-mine cell is still hidden, not won yet
            if (!$cell['isMine'] && !$cell['isRevealed']) {
                return false;
            }
        }
    }
    return true;
};


$floodFill = function ($row, $col) {
    $cell = $this->grid[$row][$col];
    if ($cell['isRevealed'] || $cell['isFlagged']) {
        return;
    }

    $this->grid[$row][$col]['isRevealed'] = true;

    if ($cell['isMine']) {
        $this->gameOver = true;
        return;
    }

    if ($cell['adjacentMines'] > 0) {
        return;
    }

    foreach ($this->getNeighbours($row, $col) as [$nr, $nc]) {
        $this->floodFill($nr, $nc);
    }

};

$revealCell = function ($row, $col) {

    if ($this->grid[$row][$col]['isFlagged'] || $this->gameOver) {
        return;
    }

    if (!$this->minesPlaced) {
        $this->placeMines($row, $col);
    }

    $this->floodFill($row, $col);

    if (!$this->gameOver && $this->checkWin()) {
        $this->won = true;
        $this->gameOver = true;
    }
};

$resetGame = function () {
    $this->reset(['grid', 'minesPlaced', 'gameOver', 'won']);
    $this->elapsedSeconds = 0;

    // reset() empties $grid back to [], but we need a fresh populated grid,
    // so rebuild it the same way mount() does
    $grid = [];
    for ($row = 0; $row < $this->rows; $row++) {
        for ($col = 0; $col < $this->cols; $col++) {
            $grid[$row][$col] = [
                'row' => $row,
                'col' => $col,
                'isMine' => false,
                'isRevealed' => false,
                'isFlagged' => false,
                'adjacentMines' => 0,
            ];
        }
    }
    $this->grid = $grid;
};

$toggleFlag = function ($row, $col) {
    if ($this->grid[$row][$col]['isRevealed']) {
        return;
    }

    $this->grid[$row][$col]['isFlagged'] = !$this->grid[$row][$col]['isFlagged'];
};


?>
<div wire:poll.1s="tick" class="flex flex-col items-center px-4 py-6">
    <h1 class="text-2xl font-bold mb-4">🧩 Minesweeper</h1>

    <div class="flex gap-6 mb-4 text-sm font-mono">
        <span>🚩 {{ $mineCount - collect($grid)->flatten(1)->where('isFlagged', true)->count() }}</span>
        <span>⏱ {{ $elapsedSeconds }}s</span>
    </div>

    @if ($gameOver)
        <p class="mb-2 text-lg font-bold {{ $won ? 'text-green-600' : 'text-red-600' }}">
            {{ $won ? '🎉 You won!' : '💥 Game over!' }}
        </p>
        <button wire:click="resetGame" class="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Play Again
        </button>
    @endif

    {{-- Board fills the available width (max ~480px on tablet+) and cells stay square via aspect-ratio --}}
    <div class="w-full max-w-sm mx-auto">
        <div
            class="grid gap-1 bg-gray-400 p-1 rounded {{ $gameOver ? 'opacity-75' : '' }}"
            style="grid-template-columns: repeat({{ $cols }}, 1fr);"
        >
            @foreach ($grid as $row)
                @foreach ($row as $cell)
                    <div
                        wire:click="revealCell({{ $cell['row'] }}, {{ $cell['col'] }})"
                        wire:contextmenu.prevent="toggleFlag({{ $cell['row'] }}, {{ $cell['col'] }})"
                        class="aspect-square flex items-center justify-center text-sm font-bold select-none cursor-pointer rounded-sm
                            {{ $cell['isRevealed'] ? 'bg-white text-gray-800' : 'bg-gray-300 hover:bg-gray-200 active:bg-gray-400' }}"
                    >
                        @if ($cell['isRevealed'])
                            @if ($cell['isMine'])
                                💣
                            @elseif ($cell['adjacentMines'] > 0)
                                {{ $cell['adjacentMines'] }}
                            @endif
                        @elseif ($gameOver && $cell['isMine'])
                            💣
                        @elseif ($cell['isFlagged'])
                            🚩
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
</div>