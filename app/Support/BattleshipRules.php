<?php

namespace App\Support;

class BattleshipRules
{
    public const SIZE = 10;

    public const FLEET = [
        ['name' => 'Carrier',    'length' => 5],
        ['name' => 'Battleship', 'length' => 4],
        ['name' => 'Cruiser',    'length' => 3],
        ['name' => 'Submarine',  'length' => 3],
        ['name' => 'Destroyer',  'length' => 2],
    ];

    public static function cellsFor(int $length, int $row, int $col, string $dir): array
    {
        $cells = [];
        for ($i = 0; $i < $length; $i++) {
            $cells[] = $dir === 'h' ? [$row, $col + $i] : [$row + $i, $col];
        }
        return $cells;
    }

    // the two constraints: every cell on the board, and none already taken
    private static function fits(array $cells, array $occupied): bool
    {
        foreach ($cells as [$r, $c]) {
            if ($r < 0 || $c < 0 || $r >= self::SIZE || $c >= self::SIZE) {
                return false;
            }
            if (isset($occupied["{$r}-{$c}"])) {
                return false;
            }
        }
        return true;
    }

    // a random legal fleet, found by trial and retry
    public static function randomFleet(): array
    {
        $occupied = [];
        $fleet = [];

        foreach (self::FLEET as $ship) {
            do {
                $dir = rand(0, 1) ? 'h' : 'v';
                $row = rand(0, self::SIZE - 1);
                $col = rand(0, self::SIZE - 1);
                $cells = self::cellsFor($ship['length'], $row, $col, $dir);
                $ok = self::fits($cells, $occupied);
            } while (! $ok);

            foreach ($cells as [$r, $c]) {
                $occupied["{$r}-{$c}"] = true;
            }
            $fleet[] = ['name' => $ship['name'], 'cells' => $cells];
        }

        return $fleet;
    }

    // fleet (names + cells) -> layout (what the browser holds)
    public static function layoutFromFleet(array $fleet): array
    {
        $layout = [];
        foreach ($fleet as $index => $ship) {
            $first = $ship['cells'][0];
            $last = $ship['cells'][count($ship['cells']) - 1];
            $layout[] = [
                'ship' => $index,
                'row'  => $first[0],
                'col'  => $first[1],
                'dir'  => $first[0] === $last[0] ? 'h' : 'v',
            ];
        }
        return $layout;
    }

    // layout (untrusted) -> fleet. Returns [fleet or null, list of errors]
    public static function fleetFromLayout(array $layout): array
    {
        $errors = [];
        $fleet = [];
        $seen = [];
        $occupied = [];

        foreach ($layout as $entry) {
            if (! is_array($entry)) {
                $errors[] = 'Bad layout.';
                continue;
            }

            $index = $entry['ship'] ?? null;
            $row = $entry['row'] ?? null;
            $col = $entry['col'] ?? null;
            $dir = $entry['dir'] ?? null;

            if (! is_int($index) || ! isset(self::FLEET[$index])) {
                $errors[] = 'Unknown ship in the layout.';
                continue;
            }

            $name = self::FLEET[$index]['name'];

            if (isset($seen[$index])) {
                $errors[] = "{$name} was sent twice.";
                continue;
            }
            $seen[$index] = true;

            if (! is_int($row) || ! is_int($col) || ! in_array($dir, ['h', 'v'], true)) {
                $errors[] = "{$name} has an invalid position.";
                continue;
            }

            // the SERVER builds the cells, from the ship's real length
            $cells = self::cellsFor(self::FLEET[$index]['length'], $row, $col, $dir);

            if (! self::fits($cells, $occupied)) {
                $errors[] = "{$name} is off the board or overlaps another ship.";
                continue;
            }

            foreach ($cells as [$r, $c]) {
                $occupied["{$r}-{$c}"] = true;
            }
            $fleet[$index] = ['name' => $name, 'cells' => $cells];
        }

        foreach (self::FLEET as $index => $ship) {
            if (! isset($seen[$index])) {
                $errors[] = "{$ship['name']} is missing.";
            }
        }

        ksort($fleet);

        return [$errors ? null : $fleet, $errors];
    }

    // per-cell shape info for drawing hulls (same as the $shipParts helper)
    public static function shipParts(array $ships): array
    {
        $parts = [];
        foreach ($ships as $index => $ship) {
            $cells = $ship['cells'];
            $last = count($cells) - 1;
            $horizontal = $cells[0][0] === $cells[$last][0];

            foreach ($cells as $i => [$r, $c]) {
                $parts["{$r}-{$c}"] = [
                    'ship'  => $index,
                    'i'     => $i,
                    'dir'   => $horizontal ? 'h' : 'v',
                    'part'  => $i === 0 ? 'stern' : ($i === $last ? 'bow' : 'mid'),
                    'cabin' => $i > 0 && $i < $last && $i === intdiv($last, 2),
                ];
            }
        }
        return $parts;
    }


    // which ship (index) sits on this cell? null if it is water
public static function findShip(array $fleet, int $row, int $col): ?int
{
    foreach ($fleet as $index => $ship) {
        foreach ($ship['cells'] as [$r, $c]) {
            if ($r === $row && $c === $col) {
                return $index;
            }
        }
    }
    return null;
}

// which ships are completely hit? $hits is a set of "row-col" keys
public static function sunkShips(array $fleet, array $hits): array
{
    $sunk = [];
    foreach ($fleet as $index => $ship) {
        $all = true;
        foreach ($ship['cells'] as [$r, $c]) {
            if (! isset($hits["{$r}-{$c}"])) {
                $all = false;
                break;
            }
        }
        if ($all) {
            $sunk[$index] = $ship;
        }
    }
    return $sunk;
}
}