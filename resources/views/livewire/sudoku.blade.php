<?php

use App\Support\SudokuRules;
use function Livewire\Volt\{state, mount};

state([
    'grid' => [],            // clues plus my digits. 0 means empty
    'selected' => null,      // [row, col] or null. The browser can edit this, so the server cleans it
    'showConflicts' => true, // display only: the clash check itself always runs
    'checking' => false,     // display only: show which of my digits are wrong
    'message' => '',         // the last hint or note
]);

$currentGame = function () {
    $g = session('sudoku');

    if (! is_array($g) || ! isset($g['puzzle'], $g['solution'], $g['difficulty'], $g['clues'])) {
        $g = SudokuRules::generate('medium');
        session(['sudoku' => $g]);
    }

    return $g;
};

mount(function () {
    $g = $this->currentGame();
    $this->grid = $g['puzzle'];
});

$newGame = function ($difficulty) {
    $difficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium';

    $g = SudokuRules::generate($difficulty);
    session(['sudoku' => $g]);

    $this->grid = $g['puzzle'];
    $this->selected = null;
    $this->checking = false;
    $this->message = '';
};

$cleanSelection = function () {
    if (!is_array($this->selected) || count($this->selected) !== 2) {
        return [-1, -1];
    }
    [$r, $c] = array_map('intval', $this->selected);

    if ($r < 0 || $r > 8 || $c < 0 || $c > 8) {
        return [-1, -1];
    }

    return [$r, $c];
};

// the selected cell, but only if it is real AND editable (not a given clue)
$editableCell = function () {
    [$r, $c] = $this->cleanSelection();

    if ($r < 0) {
        return null;
    }

    $puzzle = $this->currentGame()['puzzle'];
    if ($puzzle[$r][$c] !== 0) {
        return null; // printed in ink: it cannot be changed
    }

    return [$r, $c];
};

$wrongCells = function () {
    $game = $this->currentGame();
    $solution = $game['solution'];
    $puzzle = $game['puzzle'];
    $wrong = [];
    for ($r = 0; $r < 9; $r++) {
        for ($c = 0; $c < 9; $c++) {
            $mine = $this->grid[$r][$c];
            if ($puzzle[$r][$c] === 0 && $mine !== 0 && $mine !== $solution[$r][$c]) {
                $wrong["{$r}-{$c}"] = true;
            }
        }
    }
    return $wrong;
};

$select = function ($row, $col) {
    $row = (int) $row;
    $col = (int) $col;
    if ($row < 0 || $row > 8 || $col < 0 || $col > 8) {
        return;
    }

    $this->selected = [$row, $col];
};

$move = function ($dr, $dc) {
    [$r, $c] = $this->cleanSelection();

    if ($r < 0) {
        $this->selected = [0, 0];
        return;
    }

    $this->selected = [
        max(0, min(8, $r + (int)$dr)),
        max(0, min(8, $c + (int)$dc))
    ];
};

$enter = function ($digit) {
    $digit = (int) $digit;

    if ($digit < 1 || $digit > 9) {
        return;
    }

    if (SudokuRules::isSolved($this->grid)) {
        return; // a finished board is locked
    }

    $cell = $this->editableCell();

    if ($cell === null) {
        return;
    }
    [$r, $c] = $cell;
    $this->grid[$r][$c] = $digit;
    $this->message = '';
};

$erase = function () {
    if (SudokuRules::isSolved($this->grid)) {
        return;
    }
    $cell = $this->editableCell();
    if ($cell === null) {
        return;
    }

    [$r, $c] = $cell;
    $this->grid[$r][$c] = 0;
    $this->message = '';
};

$toggleConflicts = function () {
    $this->showConflicts = ! $this->showConflicts;
};

$toggleCheck = function () {
    $this->checking = ! $this->checking;
    $this->message = '';
};

$hint = function () {
    if (SudokuRules::isSolved($this->grid)) {
        return;
    }

    if (count($this->wrongCells()) > 0) {
        $this->message = 'Some of your digits are wrong. Fix them first, or press "Check my work" to see which.';
        return;
    }

    // 1) a forced cell: only one digit can go there
    $single = SudokuRules::nakedSingle($this->grid);
    if ($single !== null) {
        [$r, $c] = $single['cells'];
        $this->selected = [$r, $c];
        $this->message = 'Row ' . ($r + 1) . ', column ' . ($c + 1)
            . " can only be {$single['cands'][0]}: every other digit is already used by its peers.";
        return;
    }

    // 2) nothing is forced, so point at the most constrained cell
    $pick = SudokuRules::mostConstrained($this->grid);
    if ($pick !== null) {
        [$r, $c] = $pick['cells'];
        $this->selected = [$r, $c];
        $this->message = 'Nothing is forced right now. Look at row ' . ($r + 1) . ', column ' . ($c + 1)
            . ': it has only ' . count($pick['cands']) . ' candidates (' . implode(', ', $pick['cands']) . ').';
    }
};

$reveal = function () {
    if (SudokuRules::isSolved($this->grid)) {
        return;
    }
    $cell = $this->editableCell();
    if ($cell === null) {
        $this->message = 'Select an empty cell first.';
        return;
    }

    [$r, $c] = $cell;
    $this->grid[$r][$c] = $this->currentGame()['solution'][$r][$c];
    $this->message = 'Revealed.';
};

$solveAll = function () {
    $this->grid = $this->currentGame()['solution'];
    $this->message = 'Solved by the solver.';
};

$restart = function () {
    $this->grid = $this->currentGame()['puzzle'];
    $this->selected = null;
    $this->checking = false;
    $this->message = '';
};

?>
@php
    $current = session('sudoku');
    // Fallback: if session is missing render as blank (mount will fix it on next request)
    $puzzle     = $current['puzzle']     ?? \App\Support\SudokuRules::samplePuzzle();
    $solution   = $current['solution']   ?? [];
    $difficulty = $current['difficulty'] ?? 'medium';
    $clues      = $current['clues']      ?? 0;

    // Inline cleanSelection() — helper closures cannot be called via $this-> in @php blocks
    if (is_array($selected) && count($selected) === 2) {
        [$sr, $sc] = array_map('intval', $selected);
        if ($sr < 0 || $sr > 8 || $sc < 0 || $sc > 8) {
            $sr = $sc = -1;
        }
    } else {
        $sr = $sc = -1;
    }

    $conflicts = \App\Support\SudokuRules::conflicts($grid);

    // Inline wrongCells() for the same reason
    $wrong = [];
    if ($checking && $solution) {
        for ($wr = 0; $wr < 9; $wr++) {
            for ($wc = 0; $wc < 9; $wc++) {
                $mine = $grid[$wr][$wc];
                if ($puzzle[$wr][$wc] === 0 && $mine !== 0 && $mine !== $solution[$wr][$wc]) {
                    $wrong["{$wr}-{$wc}"] = true;
                }
            }
        }
    }

    $filled = collect($grid)->flatten()->filter()->count();
    $solved = $filled === 81 && count($conflicts) === 0;

    $selectedIsEmpty = $sr >= 0 && $grid[$sr][$sc] === 0;
    $cands = $selectedIsEmpty ? \App\Support\SudokuRules::candidates($grid, $sr, $sc) : [];
@endphp

<div
    x-data
    x-on:keydown.window="
        if ($event.ctrlKey || $event.metaKey || $event.altKey) return;
        if (/^[1-9]$/.test($event.key)) $wire.enter($event.key);
        else if ($event.key === 'Backspace' || $event.key === 'Delete' || $event.key === '0') $wire.erase();
        else if ($event.key === 'ArrowUp') { $event.preventDefault(); $wire.move(-1, 0); }
        else if ($event.key === 'ArrowDown') { $event.preventDefault(); $wire.move(1, 0); }
        else if ($event.key === 'ArrowLeft') { $event.preventDefault(); $wire.move(0, -1); }
        else if ($event.key === 'ArrowRight') { $event.preventDefault(); $wire.move(0, 1); }
    "
    class="flex flex-col items-center p-4"
>
    <h1 class="text-2xl font-bold mb-1">🔢 Sudoku</h1>
    <p class="text-sm text-gray-500 mb-1">
        {{ ucfirst($difficulty) }} · {{ $clues }} clues ·
        {{ $filled }} / 81 filled
        @if ($showConflicts && count($conflicts))
            · <span class="text-red-600 font-semibold">{{ count($conflicts) }} clashing cells</span>
        @endif
        @if ($checking)
            @if (count($wrong))
                · <span class="text-amber-700 font-semibold">{{ count($wrong) }} wrong digits</span>
            @elseif (! $solved)
                · <span class="text-green-700 font-semibold">no wrong digits so far</span>
            @endif
        @endif
    </p>

    {{-- new puzzle: easy, medium, hard --}}
    <div class="flex items-center gap-2 mb-3">
        @foreach (['easy', 'medium', 'hard'] as $level)
            <button wire:click="newGame('{{ $level }}')"
                wire:loading.attr="disabled" wire:target="newGame"
                class="px-3 py-1 rounded text-sm font-semibold {{ $difficulty === $level ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">
                New {{ $level }}
            </button>
        @endforeach
        <span wire:loading wire:target="newGame" class="text-sm text-gray-500 animate-pulse">Generating…</span>
    </div>

    @if ($solved)
        <p class="mb-2 text-lg font-bold text-green-600">🎉 Solved! Every row, column and box is complete.</p>
    @endif

    <div class="inline-grid border-[3px] border-slate-600 rounded"
        style="grid-template-columns: repeat(9, minmax(0, 1fr));">
        @for ($r = 0; $r < 9; $r++)
            @for ($c = 0; $c < 9; $c++)
                @php
                    $v = $grid[$r][$c];
                    $isGiven = $puzzle[$r][$c] !== 0;
                    $isSel = $r === $sr && $c === $sc;
                    $isPeer = ! $isSel && $sr >= 0 && (
                        $r === $sr || $c === $sc
                        || (intdiv($r, 3) === intdiv($sr, 3) && intdiv($c, 3) === intdiv($sc, 3))
                    );
                    $isBad = $showConflicts && isset($conflicts["{$r}-{$c}"]);
                    $isWrong = isset($wrong["{$r}-{$c}"]);

                    $rightEdge = ($c % 3 === 2 && $c < 8) ? 'border-r-[3px] border-r-slate-600' : 'border-r border-r-slate-300';
                    $bottomEdge = ($r % 3 === 2 && $r < 8) ? 'border-b-[3px] border-b-slate-600' : 'border-b border-b-slate-300';

                    $bg = $isSel ? 'bg-blue-300'
                        : ($isBad ? 'bg-red-200'
                        : ($isWrong ? 'bg-amber-200'
                        : ($isPeer ? 'bg-blue-100'
                        : ($isGiven ? 'bg-gray-100' : 'bg-white'))));

                    $text = $isBad ? 'text-red-600 font-bold'
                        : ($isWrong ? 'text-amber-800 font-bold'
                        : ($isGiven ? 'text-gray-900 font-bold' : 'text-blue-600 font-semibold'));
                @endphp
                <button type="button"
                    wire:key="cell-{{ $r }}-{{ $c }}"
                    wire:click="select({{ $r }}, {{ $c }})"
                    class="w-9 h-9 sm:w-11 sm:h-11 flex items-center justify-center text-lg sm:text-xl select-none {{ $rightEdge }} {{ $bottomEdge }} {{ $bg }} {{ $text }}">
                    {{ $v ?: '' }}
                </button>
            @endfor
        @endfor
    </div>

    {{-- what the selected empty cell may still hold --}}
    <p class="mt-3 h-5 text-sm text-gray-600">
        @if ($selectedIsEmpty)
            @if (count($cands))
                Possible here: <b>{{ implode(' ', $cands) }}</b>
            @else
                <span class="text-red-600 font-semibold">No digit fits here. A dead end, so something earlier is wrong.</span>
            @endif
        @endif
    </p>

    {{-- the last hint --}}
    <p class="mt-1 min-h-[1.25rem] text-sm text-gray-800 text-center max-w-sm">{{ $message }}</p>

    <div class="grid grid-cols-5 gap-2 mt-2 w-full max-w-xs">
        @foreach (range(1, 9) as $d)
            <button wire:click="enter({{ $d }})"
                class="py-2 rounded bg-gray-200 text-lg font-semibold {{ $selectedIsEmpty && ! in_array($d, $cands, true) ? 'opacity-40' : '' }}">
                {{ $d }}
            </button>
        @endforeach
        <button wire:click="erase" class="py-2 rounded bg-gray-200 text-sm font-semibold">⌫</button>
    </div>

    <div class="flex flex-wrap justify-center gap-2 mt-3">
        <button wire:click="hint" class="px-3 py-2 rounded bg-amber-400 text-sm font-semibold">💡 Hint</button>
        <button wire:click="reveal" class="px-3 py-2 rounded bg-gray-200 text-sm">Reveal cell</button>
        <button wire:click="toggleCheck" class="px-3 py-2 rounded bg-gray-200 text-sm">
            Check my work: {{ $checking ? 'on' : 'off' }}
        </button>
        <button wire:click="solveAll" class="px-3 py-2 rounded bg-gray-200 text-sm">Solve it</button>
        <button wire:click="toggleConflicts" class="px-3 py-2 rounded bg-gray-200 text-sm">
            Show clashes: {{ $showConflicts ? 'on' : 'off' }}
        </button>
        <button wire:click="restart" class="px-3 py-2 rounded bg-gray-200 text-sm">Restart</button>
    </div>
</div>
