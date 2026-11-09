<?php

use App\Support\SudokuRules;
use function Livewire\Volt\{state, mount};

state([
    'grid' => [],            // clues plus my digits. 0 means empty
    'notes' => [],           // pencil marks: ["row-col" => [digits]]
    'notesMode' => false,    // when on, the pad writes pencil marks instead of digits
    'selected' => null,      // [row, col] or null. The browser can edit this, so the server cleans it
    'showConflicts' => true, // display only
    'checking' => false,     // display only
    'message' => '',         // the last hint or note
]);

// Start a brand-new game: the puzzle, the answer, the progress and the clock all live in the SESSION
$startGame = function ($difficulty) {
    $g = SudokuRules::generate($difficulty);

    $g['grid'] = $g['puzzle'];   // my progress starts as just the clues
    $g['notes'] = [];
    $g['startedAt'] = time();    // the clock: a timestamp, not a counter
    $g['finishedAt'] = null;

    session(['sudoku' => $g]);

    return $g;
};

$currentGame = function () {
    $g = session('sudoku');

    // an older game from Stage 4 has no clock, so it is simply replaced
    if (! is_array($g) || ! isset($g['puzzle'], $g['solution'], $g['difficulty'], $g['clues'], $g['startedAt'], $g['grid'], $g['notes'])) {
        $g = $this->startGame('medium');
    }

    return $g;
};

// save my digits and notes, and stop the clock the first time the board is solved
$persist = function () {
    $g = $this->currentGame();

    $g['grid'] = $this->grid;
    $g['notes'] = $this->notes;

    if (($g['finishedAt'] ?? null) === null && SudokuRules::isSolved($this->grid)) {
        $g['finishedAt'] = time();
    }

    session(['sudoku' => $g]);
};

mount(function () {
    $g = $this->currentGame();

    $this->grid = $g['grid'];
    $this->notes = $g['notes'];
});

$newGame = function ($difficulty) {
    $difficulty = in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium';

    $g = $this->startGame($difficulty);

    $this->grid = $g['grid'];
    $this->notes = [];
    $this->selected = null;
    $this->checking = false;
    $this->message = '';
};

// the same puzzle again, from the start, with a fresh clock
$restart = function () {
    $g = $this->currentGame();

    $g['grid'] = $g['puzzle'];
    $g['notes'] = [];
    $g['startedAt'] = time();
    $g['finishedAt'] = null;
    session(['sudoku' => $g]);

    $this->grid = $g['puzzle'];
    $this->notes = [];
    $this->selected = null;
    $this->checking = false;
    $this->message = '';
};

// the selection as two clean whole numbers on the board, or [-1, -1] for "nothing selected"
$cleanSelection = function () {
    if (! is_array($this->selected) || count($this->selected) !== 2) {
        return [-1, -1];
    }

    [$r, $c] = array_map('intval', array_values($this->selected));

    if ($r < 0 || $r > 8 || $c < 0 || $c > 8) {
        return [-1, -1];
    }

    return [$r, $c];
};

// the selected cell, but only if it is editable (not a printed clue)
$editableCell = function () {
    [$r, $c] = $this->cleanSelection();

    if ($r < 0) {
        return null;
    }
    if ($this->currentGame()['puzzle'][$r][$c] !== 0) {
        return null; // printed in ink: it cannot be changed
    }

    return [$r, $c];
};

// my digits that differ from the real answer. Derived on demand. The answer never leaves the server
$wrongCells = function () {
    $g = $this->currentGame();
    $wrong = [];

    for ($r = 0; $r < 9; $r++) {
        for ($c = 0; $c < 9; $c++) {
            $mine = $this->grid[$r][$c];

            if ($g['puzzle'][$r][$c] === 0 && $mine !== 0 && $mine !== $g['solution'][$r][$c]) {
                $wrong["{$r}-{$c}"] = true;
            }
        }
    }

    return $wrong;
};

// add the digit to this cell's pencil notes, or remove it if it is already there
$flipNote = function ($r, $c, $digit) {
    $r = (int) $r;
    $c = (int) $c;
    $digit = (int) $digit;

    if ($r < 0 || $r > 8 || $c < 0 || $c > 8 || $digit < 1 || $digit > 9) {
        return;
    }
    // pencil marks only go on an empty, editable cell
    if ($this->currentGame()['puzzle'][$r][$c] !== 0 || $this->grid[$r][$c] !== 0) {
        return;
    }

    $key = "{$r}-{$c}";
    $list = is_array($this->notes[$key] ?? null) ? $this->notes[$key] : [];

    if (in_array($digit, $list, true)) {
        $list = array_values(array_diff($list, [$digit]));
    } else {
        $list[] = $digit;
        sort($list);
    }

    if ($list === []) {
        unset($this->notes[$key]);
    } else {
        $this->notes[$key] = $list;
    }
};

// a digit went into (r, c), so its 20 peers can no longer hold it: rub it out of their notes
$cleanPeerNotes = function ($r, $c, $digit) {
    foreach (SudokuRules::peers($r, $c) as [$pr, $pc]) {
        $key = "{$pr}-{$pc}";

        if (! is_array($this->notes[$key] ?? null)) {
            continue;
        }

        $list = array_values(array_diff($this->notes[$key], [$digit]));

        if ($list === []) {
            unset($this->notes[$key]);
        } else {
            $this->notes[$key] = $list;
        }
    }
};

$select = function ($row, $col) {
    $row = (int) $row;
    $col = (int) $col;

    if ($row < 0 || $row > 8 || $col < 0 || $col > 8) {
        return;
    }

    $this->selected = [$row, $col];
};

// arrow keys
$move = function ($dr, $dc) {
    [$r, $c] = $this->cleanSelection();

    if ($r < 0) {
        $this->selected = [0, 0];
        return;
    }

    $this->selected = [
        max(0, min(8, $r + (int) $dr)),
        max(0, min(8, $c + (int) $dc)),
    ];
};

$toggleNotesMode = function () {
    $this->notesMode = ! $this->notesMode;
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

    // pencil mode: write a small note instead of a real digit
    if ($this->notesMode) {
        $this->flipNote($r, $c, $digit);
        $this->persist();
        return;
    }

    $this->grid[$r][$c] = $digit;
    unset($this->notes["{$r}-{$c}"]);        // a real digit replaces this cell's notes
    $this->cleanPeerNotes($r, $c, $digit);   // and its peers can no longer hold it
    $this->message = '';
    $this->persist();
};

// erase a digit, or (if the cell is empty) its pencil notes
$erase = function () {
    if (SudokuRules::isSolved($this->grid)) {
        return;
    }

    $cell = $this->editableCell();
    if ($cell === null) {
        return;
    }

    [$r, $c] = $cell;

    if ($this->grid[$r][$c] !== 0) {
        $this->grid[$r][$c] = 0;
    } else {
        unset($this->notes["{$r}-{$c}"]);
    }

    $this->message = '';
    $this->persist();
};

// write every empty cell's candidates as notes (the rules' answer becomes my starting notes)
$fillNotes = function () {
    if (SudokuRules::isSolved($this->grid)) {
        return;
    }

    $notes = [];

    for ($r = 0; $r < 9; $r++) {
        for ($c = 0; $c < 9; $c++) {
            $cands = SudokuRules::candidates($this->grid, $r, $c);

            if ($cands !== []) {
                $notes["{$r}-{$c}"] = $cands;
            }
        }
    }

    $this->notes = $notes;
    $this->persist();
};

$clearNotes = function () {
    $this->notes = [];
    $this->persist();
};

$toggleConflicts = function () {
    $this->showConflicts = ! $this->showConflicts;
};

$toggleCheck = function () {
    $this->checking = ! $this->checking;
    $this->message = '';
};

// a TEACHING hint: point at a cell and explain why. It does not fill anything in
$hint = function () {
    if (SudokuRules::isSolved($this->grid)) {
        return;
    }

    // a hint on top of a wrong digit would mislead, so ask for a fix first
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

// a GIVING-UP hint: fill the selected cell with the real answer
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
    $digit = $this->currentGame()['solution'][$r][$c];

    $this->grid[$r][$c] = $digit;
    unset($this->notes["{$r}-{$c}"]);
    $this->cleanPeerNotes($r, $c, $digit);
    $this->message = 'Revealed.';
    $this->persist();
};

$solveAll = function () {
    $this->grid = $this->currentGame()['solution'];
    $this->notes = [];
    $this->message = 'Solved by the solver.';
    $this->persist();
};
?>

@php
    $current = $this->currentGame();
    $puzzle = $current['puzzle'];
    [$sr, $sc] = $this->cleanSelection();

    $conflicts = \App\Support\SudokuRules::conflicts($grid);
    $wrong = $checking ? $this->wrongCells() : [];
    $filled = collect($grid)->flatten()->filter()->count();
    $solved = $filled === 81 && count($conflicts) === 0;

    $selectedIsEmpty = $sr >= 0 && $grid[$sr][$sc] === 0;
    $cands = $selectedIsEmpty ? \App\Support\SudokuRules::candidates($grid, $sr, $sc) : [];
    $counts = \App\Support\SudokuRules::digitCounts($grid);

    // the clock: the SERVER works out the elapsed seconds, Alpine only counts up from there
    $finishedAt = $current['finishedAt'] ?? null;
    $elapsed = max(0, ($finishedAt ?? time()) - $current['startedAt']);
    $timeLabel = $elapsed >= 3600 ? gmdate('G:i:s', $elapsed) : gmdate('i:s', $elapsed);
@endphp

<div
    x-data
    x-on:keydown.window="
        if ($event.ctrlKey || $event.metaKey || $event.altKey) return;
        if (/^[1-9]$/.test($event.key)) $wire.enter($event.key);
        else if ($event.key === 'Backspace' || $event.key === 'Delete' || $event.key === '0') $wire.erase();
        else if ($event.key === 'n' || $event.key === 'N') $wire.toggleNotesMode();
        else if ($event.key === 'ArrowUp') { $event.preventDefault(); $wire.move(-1, 0); }
        else if ($event.key === 'ArrowDown') { $event.preventDefault(); $wire.move(1, 0); }
        else if ($event.key === 'ArrowLeft') { $event.preventDefault(); $wire.move(0, -1); }
        else if ($event.key === 'ArrowRight') { $event.preventDefault(); $wire.move(0, 1); }
    "
    class="flex flex-col items-center p-4"
>
    <h1 class="text-2xl font-bold mb-1">🔢 Sudoku</h1>

    <p class="text-sm text-gray-500 mb-1 text-center">
        {{ ucfirst($current['difficulty']) }} · {{ $current['clues'] }} clues ·
        {{ $filled }} / 81 filled ·
        ⏱
        {{-- a new key whenever the game restarts or finishes, so Alpine starts this timer fresh --}}
        <span wire:key="timer-{{ $current['startedAt'] }}-{{ $finishedAt ?? 'run' }}"
              x-data="{
                  base: {{ $elapsed }},
                  running: {{ $finishedAt === null ? 'true' : 'false' }},
                  t0: performance.now(),
                  now: 0,
                  init() {
                      if (! this.running) return;
                      const id = setInterval(() => {
                          if (! this.$el.isConnected) { clearInterval(id); return; }
                          this.now = Math.floor((performance.now() - this.t0) / 1000);
                      }, 1000);
                  },
                  get shown() {
                      const s = this.base + this.now;
                      const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
                      const mm = String(m).padStart(2, '0'), ss = String(sec).padStart(2, '0');
                      return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
                  }
              }"
              x-text="shown"
              class="font-mono font-semibold text-gray-700">{{ $timeLabel }}</span>
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
                class="px-3 py-1 rounded text-sm font-semibold touch-manipulation {{ $current['difficulty'] === $level ? 'bg-blue-600 text-white' : 'bg-gray-200' }}">
                New {{ $level }}
            </button>
        @endforeach
        <span wire:loading wire:target="newGame" class="text-sm text-gray-500 animate-pulse">Generating…</span>
    </div>

    @if ($solved)
        <p class="mb-2 text-lg font-bold text-green-600">🎉 Solved in {{ $timeLabel }}! Every row, column and box is complete.</p>
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

                    // pencil marks for this cell (only whole digits 1 to 9 are ever drawn)
                    $rawNotes = $notes["{$r}-{$c}"] ?? [];
                    $cellNotes = is_array($rawNotes) ? array_values(array_filter($rawNotes, fn ($n) => is_int($n) && $n >= 1 && $n <= 9)) : [];

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
                    class="w-[min(9.6vw,2.75rem)] h-[min(9.6vw,2.75rem)] flex items-center justify-center text-lg sm:text-xl select-none touch-manipulation {{ $rightEdge }} {{ $bottomEdge }} {{ $bg }} {{ $text }}">
                    @if ($v === 0 && count($cellNotes))
                        <span class="grid grid-cols-3 w-full h-full p-px text-[9px] leading-none font-normal text-slate-500">
                            @foreach (range(1, 9) as $n)
                                <span class="flex items-center justify-center">{{ in_array($n, $cellNotes, true) ? $n : '' }}</span>
                            @endforeach
                        </span>
                    @else
                        {{ $v ?: '' }}
                    @endif
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

    <div class="mt-2 w-full max-w-md px-1">
        {{-- the number pad: one row of nine big buttons --}}
        <div class="grid grid-cols-9 gap-1">
            @foreach (range(1, 9) as $d)
                <button wire:click="enter({{ $d }})"
                    class="h-12 rounded text-xl font-semibold touch-manipulation select-none {{ $notesMode ? 'bg-amber-200' : 'bg-gray-200' }}
                        {{ $counts[$d] >= 9 ? 'opacity-30' : '' }}
                        {{ ! $notesMode && $selectedIsEmpty && ! in_array($d, $cands, true) ? 'opacity-40' : '' }}">
                    {{ $d }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-3 gap-2 mt-2">
            <button wire:click="erase" class="h-11 rounded bg-gray-200 text-sm font-semibold touch-manipulation">⌫ Erase</button>
            <button wire:click="toggleNotesMode"
                class="h-11 rounded text-sm font-semibold touch-manipulation {{ $notesMode ? 'bg-amber-400' : 'bg-gray-200' }}">
                ✏️ Notes: {{ $notesMode ? 'on' : 'off' }}
            </button>
            <button wire:click="hint" class="h-11 rounded bg-amber-400 text-sm font-semibold touch-manipulation">💡 Hint</button>
        </div>

        <details class="mt-3">
            <summary class="cursor-pointer text-sm text-gray-600 select-none">More tools</summary>
            <div class="flex flex-wrap gap-2 mt-2">
                <button wire:click="fillNotes" class="px-3 py-2 rounded bg-gray-200 text-sm touch-manipulation">Fill notes</button>
                <button wire:click="clearNotes" class="px-3 py-2 rounded bg-gray-200 text-sm touch-manipulation">Clear notes</button>
                <button wire:click="reveal" class="px-3 py-2 rounded bg-gray-200 text-sm touch-manipulation">Reveal cell</button>
                <button wire:click="toggleCheck" class="px-3 py-2 rounded bg-gray-200 text-sm touch-manipulation">
                    Check my work: {{ $checking ? 'on' : 'off' }}
                </button>
                <button wire:click="toggleConflicts" class="px-3 py-2 rounded bg-gray-200 text-sm touch-manipulation">
                    Show clashes: {{ $showConflicts ? 'on' : 'off' }}
                </button>
                <button wire:click="solveAll" class="px-3 py-2 rounded bg-gray-200 text-sm touch-manipulation">Solve it</button>
                <button wire:click="restart" class="px-3 py-2 rounded bg-gray-200 text-sm touch-manipulation">Restart</button>
            </div>
        </details>
    </div>
</div>