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
    'flagMode' => false,
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
    if ($this->gameOver) {
        return;
    }

    if ($this->flagMode) {
        $this->toggleFlag($row, $col);
        return;
    }

    if ($this->grid[$row][$col]['isFlagged']) {
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
    $this->reset(['grid', 'minesPlaced', 'gameOver', 'won', 'flagMode']);
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

    @php
        $numColors = [
            1 => 'text-blue-600',
            2 => 'text-emerald-600',
            3 => 'text-red-600',
            4 => 'text-indigo-800',
            5 => 'text-amber-800',
            6 => 'text-teal-600',
            7 => 'text-purple-800',
            8 => 'text-gray-600',
        ];
    @endphp

    <div class="flex flex-col lg:flex-row items-center lg:items-start justify-center gap-8 w-full max-w-4xl">
        {{-- Game Board Column --}}
        <div class="flex flex-col items-center w-full max-w-sm">
            <div class="flex items-center justify-between w-full mb-3 px-1 text-sm font-mono">
                <span class="font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded border border-slate-200">
                    🚩 {{ $mineCount - collect($grid)->flatten(1)->where('isFlagged', true)->count() }}
                </span>
                <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-lg border border-slate-200 text-xs font-sans">
                    <button wire:click="$set('flagMode', false)"
                        class="px-2.5 py-1 rounded font-semibold transition {{ ! $flagMode ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        ⛏️ Dig
                    </button>
                    <button wire:click="$set('flagMode', true)"
                        class="px-2.5 py-1 rounded font-semibold transition {{ $flagMode ? 'bg-red-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        🚩 Flag
                    </button>
                </div>
                <span class="font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded border border-slate-200">
                    ⏱ {{ $elapsedSeconds }}s
                </span>
            </div>

            @if ($gameOver)
                <p class="mb-2 text-lg font-bold {{ $won ? 'text-green-600' : 'text-red-600' }}">
                    {{ $won ? '🎉 You won!' : '💥 Game over!' }}
                </p>
                <button wire:click="resetGame" class="mb-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-semibold text-sm shadow">
                    Play Again
                </button>
            @endif

            <div class="w-full">
                <div
                    class="grid gap-1 bg-slate-400 p-1 rounded-lg shadow-inner {{ $gameOver ? 'opacity-90' : '' }}"
                    style="grid-template-columns: repeat({{ $cols }}, 1fr);"
                >
                    @foreach ($grid as $row)
                        @foreach ($row as $cell)
                            @php
                                $isHitMine = $gameOver && ! $won && $cell['isMine'] && $cell['isRevealed'];
                            @endphp
                            <div
                                wire:key="cell-{{ $cell['row'] }}-{{ $cell['col'] }}"
                                wire:click="revealCell({{ $cell['row'] }}, {{ $cell['col'] }})"
                                wire:contextmenu.prevent="toggleFlag({{ $cell['row'] }}, {{ $cell['col'] }})"
                                class="aspect-square flex items-center justify-center text-base font-extrabold select-none cursor-pointer rounded transition-colors
                                    {{ $cell['isRevealed'] ? ($isHitMine ? 'bg-red-500 text-white' : 'bg-slate-100') : 'bg-slate-300 hover:bg-slate-200 active:bg-slate-400' }}"
                            >
                                @if ($cell['isRevealed'])
                                    @if ($cell['isMine'])
                                        💣
                                    @elseif ($cell['adjacentMines'] > 0)
                                        <span class="{{ $numColors[$cell['adjacentMines']] ?? 'text-gray-800' }}">
                                            {{ $cell['adjacentMines'] }}
                                        </span>
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

        {{-- Side Column: How to play --}}
        <div id="how-to-play" class="w-full max-w-sm lg:w-72 flex-shrink-0">
            <div class="border border-gray-200 bg-white rounded-lg p-4 text-xs text-gray-600 space-y-3">
                <h2 class="font-semibold text-gray-900 text-sm border-b border-gray-100 pb-2">
                    How to play
                </h2>
                <div>
                    <span class="font-medium text-gray-800">Objective:</span>
                    <p class="mt-0.5 text-gray-600">Reveal all safe squares without detonating any mines.</p>
                </div>
                <div>
                    <span class="font-medium text-gray-800">Rules:</span>
                    <ul class="list-disc pl-4 mt-1 space-y-1 text-gray-600">
                        <li>Click any square to uncover it.</li>
                        <li>Numbers indicate how many mines touch that square.</li>
                        <li>Right-click (or toggle Flag mode) to mark suspected mines with a flag.</li>
                    </ul>
                </div>
                <div class="pt-2 border-t border-gray-100 text-gray-500">
                    <span class="font-medium text-gray-700">Tip:</span> If a 1 touches only one covered square, that square is guaranteed to be a mine.
                </div>
            </div>
        </div>
    </div>
</div>