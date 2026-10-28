<?php
use function Livewire\Volt\{state, mount};

state([
    'size' => 10,
    'fleet' => [
        ['name' => 'Carrier', 'length' => 5],
        ['name' => 'Battleship', 'length' => 4],
        ['name' => 'Cruiser', 'length' => 3],
        ['name' => 'Submarine', 'length' => 3],
        ['name' => 'Destroyer', 'length' => 2],
    ],
    'myGrid' => [],        // your ocean: where YOUR ships are
    'trackingGrid' => [],  // what you know about the ENEMY's ocean
    'gameOver' => false,
    'won' => false,
    'message' => '',
    'computerMessage' => '',
    'phase' => 'player',   // 'player' or 'enemy'
    'lastShot' => null,    // [row, col] of the computer's latest shot
    'myShips' => [],
]);

mount(function () {
    $this->newGame();
});

$makeGrid = function ($cellDefaults) {
    $grid = [];
    for ($row = 0; $row < $this->size; $row++) {
        for ($col = 0; $col < $this->size; $col++) {
            $grid[$row][$col] = array_merge(
                ['row' => $row, 'col' => $col],
                $cellDefaults
            );
        }
    }
    return $grid;
};

$placeFleet = function () {
    $occupied = [];
    $ships = [];

    foreach ($this->fleet as $ship) {
        $placed = false;
        while (!$placed) {
            $horizontal = (bool) rand(0, 1);
            $row = rand(0, $this->size - 1);
            $col = rand(0, $this->size - 1);

            $cell = [];
            for ($i = 0; $i < $ship['length']; $i++) {
                $cell[] = [
                    $horizontal ? $row : $row + $i,
                    $horizontal ? $col + $i : $col
                ];
            };
            $valid = true;
            foreach ($cell as [$r, $c]) {
                if ($r >= $this->size || $c >= $this->size || isset($occupied["{$r}-{$c}"])) {
                    $valid = false;
                    break;
                }

            }
            if (!$valid) {
                continue;
            }

            foreach ($cell as [$r, $c]) {
                $occupied["{$r}-{$c}"] = true;
            }


            $ships[] = [
                'name' => $ship['name'],
                'cells' => $cell,
            ];
            $placed = true;
        }
    }
    return $ships;

};

$findShip = function ($fleet, $row, $col) {
    foreach ($fleet as $index => $ship) {
        foreach ($ship['cells'] as [$r, $c]) {
            if ($r === $row && $c === $col) {
                return $index;
            }
        }
    }
    return null;
};

$pickComputerShot = function () {
    $remaining = $this->remainingCells();

    $unshot = [];
    $liveHits = []; // cells the computer hit, on ships that are still afloat
    for ($r = 0; $r < $this->size; $r++) {
        for ($c = 0; $c < $this->size; $c++) {
            $cell = $this->myGrid[$r][$c];
            if (!$cell['isShot']) {
                $unshot[] = [$r, $c];
            } elseif ($cell['shipIndex'] !== null && isset($remaining[$cell['shipIndex']])) {
                $liveHits["{$r}-{$c}"] = [$r, $c];
            }
        }
    }

    // TARGET mode: score the 4 neighbours of every live hit
    $scores = [];
    foreach ($liveHits as [$r, $c]) {
        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dr, $dc]) {
            $nr = $r + $dr;
            $nc = $c + $dc;

            if ($nr < 0 || $nr >= $this->size || $nc < 0 || $nc >= $this->size) {
                continue; // off the board
            }
            if ($this->myGrid[$nr][$nc]['isShot']) {
                continue; // already fired here
            }

            // is the cell behind the hit ALSO a live hit? then this extends a line
            $inLine = isset($liveHits[($r - $dr) . '-' . ($c - $dc)]);
            $key = "{$nr}-{$nc}";
            $scores[$key] = max($scores[$key] ?? 0, $inLine ? 2 : 1);
        }
    }

    if (!empty($scores)) {
        $best = max($scores);
        $top = array_keys(array_filter($scores, fn($s) => $s === $best));
        $pick = $top[rand(0, count($top) - 1)];
        return array_map('intval', explode('-', $pick));
    }

    // HUNT mode: no live hits, so search the checkerboard
    $parity = array_values(array_filter($unshot, fn($p) => ($p[0] + $p[1]) % 2 === 0));
    $pool = !empty($parity) ? $parity : $unshot;

    return $pool[rand(0, count($pool) - 1)];
};

$computerTurn = function () {
    if ($this->gameOver || $this->phase !== 'enemy') {
        return;
    }
    $this->phase = 'player'; // the turn passes back, whichever way this function ends
    [$r, $c] = $this->pickComputerShot();
    $this->myGrid[$r][$c]['isShot'] = true;
    $this->lastShot = [$r, $c];

    $shipIndex = $this->myGrid[$r][$c]['shipIndex'];
    if ($shipIndex === null) {
        $this->computerMessage = 'The enemy fired and missed.';
        return;
    }

    $remaining = $this->remainingCells();
    $name = $this->fleet[$shipIndex]['name'];
    $this->computerMessage = isset($remaining[$shipIndex])
        ? "The enemy hit your {$name}!"
        : "The enemy sank your {$name}!";

    if (empty($remaining)) { // none of your ships has an unshot cell left
        $this->gameOver = true;
        $this->won = false;
        $this->message = 'The enemy sank your whole fleet. You lose!';
    }
};

$sunkShips = function () {
    $sunk = [];
    foreach (session('battleship.enemy', []) as $ship) {
        $allHit = true;
        foreach ($ship['cells'] as [$r, $c]) {
            if ($this->trackingGrid[$r][$c]['status'] !== 'hit') {
                $allHit = false;
                break;
            }
        }
        if ($allHit) {
            $sunk[] = $ship['name'];
        }
    }
    return $sunk;
};


$remainingCells = function () {
    $remaining = [];
    for ($r = 0; $r < $this->size; $r++) {
        for ($c = 0; $c < $this->size; $c++) {
            $cell = $this->myGrid[$r][$c];
            if ($cell['shipIndex'] !== null && !$cell['isShot']) {
                $index = $cell['shipIndex'];
                $remaining[$index] = ($remaining[$index] ?? 0) + 1;
            }
        }
    }
    return $remaining; // a ship with NO entry here is sunk
};

$fire = function ($row, $col) {
    $row = (int) $row;
    $col = (int) $col;

    if ($this->gameOver || $this->phase !== 'player') {
        return;
    }
    if ($row < 0 || $row >= $this->size || $col < 0 || $col >= $this->size) {
        return;
    }

    if ($this->trackingGrid[$row][$col]['status'] !== 'unknown') {
        return;
    }

    $enemy = session('battleship.enemy', []);
    if (empty($enemy)) {
        $this->newGame();
        $this->message = 'Session expired, so a new game was started.';
        return;
    }

    $hitShip = $this->findShip($enemy, $row, $col); // return null if no ship is found or return index of that ship found

    if ($hitShip === null) {
        $this->trackingGrid[$row][$col]['status'] = 'miss';
        $this->message = 'Miss.';
    } else {
        $this->trackingGrid[$row][$col]['status'] = 'hit';
        $this->message = 'Hit!';

        $sunk = $this->sunkShips();

        if (in_array($enemy[$hitShip]['name'], $sunk)) {
            $this->message = 'You sunk the ' . $enemy[$hitShip]['name'] . '!';
        }

        if (count($sunk) === count($enemy)) {
            $this->won = true;
            $this->gameOver = true;
            $this->message = 'You sunk the whole enemy fleet. You win!';
            $this->computerMessage = '';
            return;
        }
    }
    $this->phase = 'enemy';
    $this->dispatch('enemy-turn'); // the browser will call computerTurn after a short pause
};

$sunkEnemyCells = function () {
    $sunk = [];
    foreach (session('battleship.enemy', []) as $index => $ship) {
        $allHit = true;
        foreach ($ship['cells'] as [$r, $c]) {
            if ($this->trackingGrid[$r][$c]['status'] !== 'hit') {
                $allHit = false;
                break;
            }
        }
        if ($allHit) {
            $sunk[$index] = $ship;
        }
    }
    return $this->shipParts($sunk);
};


$newGame = function () {
    $this->myGrid = $this->makeGrid([
        'shipIndex' => null,
        'isShot' => false,
    ]);

    $this->trackingGrid = $this->makeGrid([
        'status' => 'unknown',
    ]);

    $myFleet = $this->placeFleet();
    $grid = $this->myGrid;
    foreach ($myFleet as $index => $ship) {
        foreach ($ship['cells'] as [$r, $c]) {
            $grid[$r][$c]['shipIndex'] = $index;
        }
    }
    $this->myGrid = $grid;
    $this->myShips = $myFleet;
    $this->myGrid = $grid;
    $this->gameOver = false;
    $this->won = false;
    $this->message = '';
    $this->computerMessage = '';
    $this->phase = 'player';
    $this->lastShot = null;

    // enemy fleet: session only, never part of the component's state
    session(['battleship.enemy' => $this->placeFleet()]);

};

$shipParts = function ($ships) {
    $parts = [];
    foreach ($ships as $index => $ship) {
        $cells = $ship['cells'];
        $last = count($cells) - 1;
        $horizontal = $cells[0][0] === $cells[$last][0]; // same row means horizontal

        foreach ($cells as $i => [$r, $c]) {
            $parts["{$r}-{$c}"] = [
                'ship' => $index,
                'i' => $i,
                'dir' => $horizontal ? 'h' : 'v',
                'part' => $i === 0 ? 'stern' : ($i === $last ? 'bow' : 'mid'),
                'cabin' => $i > 0 && $i < $last && $i === intdiv($last, 2),
            ];
        }
    }
    return $parts;
};


?>
<div x-data="{ tilt: false }" x-on:enemy-turn.window="setTimeout(() => $wire.computerTurn(), 900)"
    class="flex flex-col items-center p-4 sm:p-6">
    <h1 class="text-2xl font-bold mb-2">🚢 Battleship</h1>
    <a href="{{ route('battleship.lobby') }}"
        class="inline-block mb-4 px-5 py-2 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white rounded-lg font-semibold text-sm transition-all shadow">
        🌐 Play Online
    </a>
    @php
        $canFire = $phase === 'player' && !$gameOver;
        $sunkEnemy = $this->sunkEnemyCells();
        $myParts = $this->shipParts($myShips);
        $remainingMine = $this->remainingCells();
    @endphp

    <p class="h-6 font-semibold {{ $won ? 'text-green-600' : ($gameOver ? 'text-red-600' : 'text-gray-700') }}">
        {{ $message }}
    </p>
    <p class="h-5 mb-1 text-sm text-gray-500 {{ $phase === 'enemy' ? 'animate-pulse' : '' }}">
        {{ $phase === 'enemy' ? 'Enemy is aiming…' : $computerMessage }}
    </p>
    <p class="text-sm text-gray-500 mb-3">
        Shots: {{ collect($trackingGrid)->flatten(1)->where('status', '!=', 'unknown')->count() }}
        · Sunk: {{ count($this->sunkShips()) }} / {{ count($fleet) }}
        @if (count($this->sunkShips()))
            ({{ implode(', ', $this->sunkShips()) }})
        @endif
    </p>
    @if ($gameOver)
        <button wire:click="newGame" class="mb-3 px-4 py-2 bg-blue-600 text-white rounded">Play Again</button>
    @endif
    <button @click="tilt = !tilt" class="mb-4 px-3 py-1 rounded bg-gray-200 text-sm">
        Toggle 3D view
    </button>
    <div class="flex flex-col lg:flex-row gap-6 lg:gap-10 items-center lg:items-start justify-center mb-6">

        {{-- enemy waters: the tracking grid --}}
        <div class="flex flex-col items-center">
            <h2 class="font-semibold mb-1">Enemy waters</h2>
            <div class="grid grid-cols-10 gap-0.5 bg-slate-400 p-1 rounded transition-transform duration-500"
                style="grid-template-columns: repeat({{ $size }}, minmax(0, 1fr)); transform-origin: center bottom;"
                :style="'grid-template-columns: repeat({{ $size }}, minmax(0, 1fr)); transform-origin: center bottom;' + (tilt ? ' transform: perspective(900px) rotateX(55deg);' : '')">
                @foreach ($trackingGrid as $row)
                    @foreach ($row as $cell)
                        @php
                            $key = $cell['row'] . '-' . $cell['col'];
                            $part = $sunkEnemy[$key] ?? null;

                            if ($part) {
                                $bg = 'water fx-cell';
                            } elseif ($cell['status'] === 'hit') {
                                $bg = 'scorch fx-cell';
                            } elseif ($cell['status'] === 'miss') {
                                $bg = 'water fx-cell';
                            } else {
                                $bg = 'fog ' . ($canFire ? 'cursor-pointer hover:brightness-150' : 'cursor-not-allowed');
                            }
                        @endphp
                        <div wire:key="track-{{ $key }}" wire:click="fire({{ $cell['row'] }}, {{ $cell['col'] }})" @if ($part)
                        style="--d: {{ $part['i'] * 0.12 }}s" @endif class="w-7 h-7 sm:w-8 sm:h-8 select-none {{ $bg }}">
                            @if ($part)
                                <span
                                    class="hull hull-{{ $part['dir'] }} {{ $part['part'] }} ship-{{ $part['ship'] }} wreck {{ $part['cabin'] ? 'cabin' : '' }}"></span>
                                <span class="boom boom-big"></span>
                                <span class="smoke"></span>
                            @elseif ($cell['status'] === 'hit')
                                <span class="boom"></span>
                                <span class="fire">🔥</span>
                            @elseif ($cell['status'] === 'miss')
                                <span class="splash"></span>
                                <span class="foam"></span>
                            @endif
                        </div>
                    @endforeach
                @endforeach

            </div>
        </div>

        {{-- your waters: your own ocean --}}
        <div class="flex flex-col items-center">
            <h2 class="font-semibold mb-1">Your fleet</h2>
            <div class="grid grid-cols-10 gap-0.5 bg-slate-400 p-1 rounded transition-transform duration-500"
                style="grid-template-columns: repeat({{ $size }}, minmax(0, 1fr)); transform-origin: center bottom;"
                :style="'grid-template-columns: repeat({{ $size }}, minmax(0, 1fr)); transform-origin: center bottom;' + (tilt ? ' transform: perspective(900px) rotateX(55deg);' : '')">
                @php
                    $shipColors = ['bg-slate-900', 'bg-slate-700', 'bg-slate-600', 'bg-slate-800', 'bg-slate-500'];
                @endphp

                @foreach ($myGrid as $row)
                    @foreach ($row as $cell)
                        @php
                            $key = $cell['row'] . '-' . $cell['col'];
                            $part = $myParts[$key] ?? null;
                            $isShot = $cell['isShot'];
                            $isSunk = $part && !isset($remainingMine[$part['ship']]);
                            $isLast = $lastShot && $lastShot[0] === $cell['row'] && $lastShot[1] === $cell['col'];
                        @endphp
                        <div wire:key="mine-{{ $key }}" @if ($isSunk) style="--d: {{ $part['i'] * 0.12 }}s" @endif
                            class="water w-7 h-7 sm:w-8 sm:h-8 select-none {{ $isShot ? 'fx-cell' : '' }} {{ $isLast ? 'last-shot' : '' }}">
                            @if ($part)
                                <span
                                    class="hull hull-{{ $part['dir'] }} {{ $part['part'] }} ship-{{ $part['ship'] }} {{ $part['cabin'] ? 'cabin' : '' }} {{ $isSunk ? 'wreck' : ($isShot ? 'hit' : '') }}"></span>
                                @if ($isSunk)
                                    <span class="boom boom-big"></span>
                                    <span class="smoke"></span>
                                @elseif ($isShot)
                                    <span class="boom"></span>
                                    <span class="fire">🔥</span>
                                @endif
                            @elseif ($isShot)
                                <span class="splash"></span>
                                <span class="foam"></span>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

    </div>
    {{-- the fleet legend --}}
    <div class="text-sm text-gray-600">
        @foreach ($fleet as $ship)
            <p>{{ $ship['name'] }}: {{ str_repeat('■ ', $ship['length']) }}</p>
        @endforeach
    </div>
</div>