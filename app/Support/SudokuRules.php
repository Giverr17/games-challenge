<?php

namespace App\Support;

class SudokuRules
{
    public const SIZE = 9;

    // a fixed sample puzzle for now. 0 means empty. Stage 4 replaces this with a generator
    public static function samplePuzzle(): array
    {
        return [
            [5, 3, 0, 0, 7, 0, 0, 0, 0],
            [6, 0, 0, 1, 9, 5, 0, 0, 0],
            [0, 9, 8, 0, 0, 0, 0, 6, 0],
            [8, 0, 0, 0, 6, 0, 0, 0, 3],
            [4, 0, 0, 8, 0, 3, 0, 0, 1],
            [7, 0, 0, 0, 2, 0, 0, 0, 6],
            [0, 6, 0, 0, 0, 0, 2, 8, 0],
            [0, 0, 0, 4, 1, 9, 0, 0, 5],
            [0, 0, 0, 0, 8, 0, 0, 7, 9],
        ];
    }

        // how many times each digit 1 to 9 appears on the board: [1 => 4, 2 => 9, ...]
    public static function digitCounts(array $grid): array
    {
        $counts = array_fill(1, 9, 0);

        foreach ($grid as $row) {
            foreach ($row as $v) {
                if ($v >= 1 && $v <= 9) {
                    $counts[$v]++;
                }
            }
        }

        return $counts;
    }

    public const TARGET_CLUES = ['easy' => 40, 'medium' => 32, 'hard' => 26];


    public static function solutionOfSample(): array
    {
        static $solution = null;

        return $solution ??= self::solve(self::samplePuzzle());
    }

    public static function emptyGrid(): array
    {
        return array_fill(0, 9, array_fill(0, 9, 0));
    }

    public static function peers(int $r, int $c): array
    {
        static $table = [];

        return $table[$r * 9 + $c] ??= self::buildPeers($r, $c);
    }

    private static function buildPeers(int $r, int $c): array
    {
        $peers = [];
        $br = intdiv($r, 3) * 3;
        $bc = intdiv($c, 3) * 3;

        for ($i = 0; $i < 9; $i++) {
            if ($i !== $c) {
                $peers["{$r}-{$i}"] = [$r, $i]; // same row
            }
            if ($i !== $r) {
                $peers["{$i}-{$c}"] = [$i, $c]; // same column
            }
        }

        for ($i = $br; $i < $br + 3; $i++) {
            for ($j = $bc; $j < $bc + 3; $j++) {
                if ($i !== $r || $j !== $c) {
                    $peers["{$i}-{$j}"] = [$i, $j]; // same box. Repeats overwrite themselves
                }
            }
        }

        return array_values($peers); // always 20
    }


    // the digits this empty cell may still hold: 1 to 9 minus what its peers hold
    public static function candidates(array $grid, int $r, int $c): array
    {

        if ($grid[$r][$c] !== 0) {
            return [];
        }

        $used = [];
        foreach (self::peers($r, $c) as [$pr, $pc]) {
            $used[$grid[$pr][$pc]] = true;
        }
        $out = [];
        for ($d = 1; $d <= 9; $d++) {
            if (!isset($used[$d])) {
                $out[] = $d;
            }
        }
        return $out;
    }

    public static function conflicts(array $grid): array
    {
        $bad = [];

        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                $v = $grid[$r][$c];
                if ($v === 0) {
                    continue;
                }
                foreach (self::peers($r, $c) as [$pr, $pc]) {
                    if ($grid[$pr][$pc] === $v) {
                        $bad["{$r}-{$c}"] = true;
                        break;
                    }
                }
            }
        }
        return $bad;
    }

    public static function isSolved(array $grid): bool
    {
        foreach ($grid as $row) {
            foreach ($row as $v) {
                if ($v === 0) {
                    return false;
                }
            }
        }
        return self::conflicts($grid) === [];
    }

    // the empty cell with the fewest candidates (MRV, "seat the fussiest guest first").
    // null: no empty cell is left. 'cands' empty: that cell is a dead end
    public static function mostConstrained(array $grid): ?array
    {
        $best = null;

        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                if ($grid[$r][$c] !== 0) {
                    continue;
                }

                $cands = self::candidates($grid, $r, $c);
                if ($cands === []) {
                    return ['cells' => [$r, $c], 'cands' => []];
                }

                if ($best === null || count($cands) < count($best['cands'])) {
                    $best = ['cells' => [$r, $c], 'cands' => $cands];
                    if (count($cands) === 1) {
                        return $best;
                    }
                }
            }
        }
        return $best;
    }

    // backtracking solver. Returns the first full solution, or null if there is none.
    // It assumes the grid it starts with has no clashes (we only call it on the fixed puzzle)
    public static function solve(array $grid): ?array
    {
        $pick = self::mostConstrained($grid);

        if ($pick === null) {
            return $grid;
        }

        if ($pick['cands'] === []) {
            return null;
        }

        [$r, $c] = $pick['cells'];

        foreach ($pick['cands'] as $d) {
            $grid[$r][$c] = $d;
            $result = self::solve($grid);

            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }


    public static function countSolutions(array $grid, int $cap): int
    {
        $pick = self::mostConstrained($grid);

        if ($pick === null) {
            return 1; // a complete grid is one solution
        }

        if ($pick['cands'] === []) {
            return 0;
        }

        [$r, $c] = $pick['cells'];
        $count = 0;


        foreach ($pick['cands'] as $d) {
            $grid[$r][$c] = $d;
            $count += self::countSolutions($grid, $cap - $count);

            if ($count >= $cap) {
                break;
            }
        }
        return $count;
    }

    public static function nakedSingle(array $grid): ?array
    {
        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                if ($grid[$r][$c] !== 0) {
                    continue;
                }
                $cands = self::candidates($grid, $r, $c);
                if (count($cands) === 1) {
                    return ['cells' => [$r, $c], 'cands' => $cands];
                }
            }
        }
        return null;
    }

    // Fisher-Yates: walk from the end, swapping each item with a random item at or before it
    public static function shuffled(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    public static function fullGrid(array $grid): ?array
    {
        $pick = self::mostConstrained($grid);

        if ($pick === null) {
            return $grid;
        }

        if ($pick['cands'] === []) {
            return null;
        }

        [$r, $c] = $pick['cells'];
        foreach (self::shuffled($pick['cands']) as $d) {
            $grid[$r][$c] = $d;
            $result = self::fullGrid($grid);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    // 1) build a full grid  2) take clues away one at a time, keeping a removal
    // only if the puzzle still has exactly ONE solution

    public static function generate(string $difficulty = 'medium'): array
    {
        if (!array_key_exists($difficulty, self::TARGET_CLUES)) {
            $difficulty = 'medium';
        }
        $target = self::TARGET_CLUES[$difficulty];
        $solution = self::fullGrid(self::emptyGrid());
        $puzzle = $solution;
        $clues = 81;

        $cells = range(0, 80);


        foreach (self::shuffled($cells) as $i) {

            if ($clues <= $target) {
                break;
            }
            $r = intdiv($i, 9);
            $c = $i % 9;

            $kept = $puzzle[$r][$c];
            $puzzle[$r][$c] = 0;

            if (self::countSolutions($puzzle, 2) === 1) {
                $clues--; // still exactly one answer: the clue stays removed
            } else {
                $puzzle[$r][$c] = $kept; // two or more: put it back, go to the next cell
            }
        }

        return [
            'puzzle' => $puzzle,
            'solution' => $solution,
            'difficulty' => $difficulty,
            'clues' => $clues,
        ];
    }
}
