<?php

use App\Events\RoomUpdated;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Support\BattleshipRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function Livewire\Volt\{state, mount, with};

state([
    'code' => '',
    'full' => false,
    'layout' => [],   // my fleet as {ship, row, col, dir}. The browser can edit this, so the server re-checks it
    'message' => '',
]);

// Who am I? The session token is checked against the database.
// Returns my GamePlayer row, or null.
$myPlayer = function () {
    $mine = session("battleship.{$this->code}");
    if (! $mine) {
        return null;
    }

    $game = Game::where('code', $this->code)->first();
    if (! $game) {
        return null;
    }

    return $game->players()
        ->where('seat', $mine['seat'])
        ->where('token', $mine['token'])
        ->first();
};

mount(function (string $code) {
    $this->code = $code;
    $game = Game::where('code', $code)->firstOrFail();

    if ($this->myPlayer() === null) {
        $token = Str::random(32);
        try {
            $game->players()->create(['seat' => 2, 'token' => $token]);
            session(["battleship.{$code}" => ['seat' => 2, 'token' => $token]]);
            $game->update(['status' => 'placing']);
            broadcast(new RoomUpdated($code, 'joined'));
        } catch (UniqueConstraintViolationException $e) {
            $this->full = true; // the database refused: seat 2 is taken
        }
    }

    // a saved fleet if I already locked in, otherwise a fresh random suggestion
    if ($player = $this->myPlayer()) {
        $this->layout = BattleshipRules::layoutFromFleet(
            $player->fleet ?: BattleshipRules::randomFleet()
        );
        $this->heartbeat();
    }
});

$reroll = function () {
    $me = $this->myPlayer();
    if (! $me || $me->ready) {
        return; // no changes after Ready. Checked here on the server, not by hiding a button
    }

    $this->message = '';
    $this->layout = BattleshipRules::layoutFromFleet(BattleshipRules::randomFleet());
};

$rotateShip = function ($ship) {
    $me = $this->myPlayer();
    if (! $me || $me->ready) {
        return;
    }

    $ship = (int) $ship;
    if (! isset(BattleshipRules::FLEET[$ship])) {
        return;
    }

    $entry = collect($this->layout)->firstWhere('ship', $ship);
    if (! $entry) {
        return;
    }

    $len = BattleshipRules::FLEET[$ship]['length'];
    $newDir = $entry['dir'] === 'h' ? 'v' : 'h';

    // try turning the ship around each of its cells, starting with the first
    for ($k = 0; $k < $len; $k++) {
        $row = $entry['dir'] === 'h' ? $entry['row'] - $k : $entry['row'] + $k;
        $col = $entry['dir'] === 'h' ? $entry['col'] + $k : $entry['col'] - $k;

        $layout = $this->layout;
        foreach ($layout as $i => $e) {
            if ($e['ship'] === $ship) {
                $layout[$i] = ['ship' => $ship, 'row' => $row, 'col' => $col, 'dir' => $newDir];
            }
        }

        [$fleet, $errors] = BattleshipRules::fleetFromLayout($layout);
        if (! $errors) {
            $this->message = '';
            $this->layout = $layout;
            return; // the first pivot that fits wins
        }
    }

    $this->message = 'No room to rotate that ship.';
};
// move or rotate one ship. The server decides, using the same validator as Ready
$placeShip = function ($ship, $row, $col, $dir) {
    $me = $this->myPlayer();
    if (! $me || $me->ready) {
        return; // locked after Ready, checked on the server
    }

    $ship = (int) $ship;
    $row = (int) $row;
    $col = (int) $col;

    if (! in_array($dir, ['h', 'v'], true) || ! isset(BattleshipRules::FLEET[$ship])) {
        return;
    }

    // try the move on a COPY of the layout, then run the full validator on it
    $layout = $this->layout;
    foreach ($layout as $i => $entry) {
        if ($entry['ship'] === $ship) {
            $layout[$i] = ['ship' => $ship, 'row' => $row, 'col' => $col, 'dir' => $dir];
        }
    }

    [$fleet, $errors] = BattleshipRules::fleetFromLayout($layout);
    if ($errors) {
        $this->message = 'Not allowed there (off the board, or on top of another ship).';
        return; // layout is unchanged, so the ship stays where it was
    }

    $this->message = '';
    $this->layout = $layout;
};

// called by wire:sort when a ship is dropped into a cell
$dropShip = function ($ship, $position, $cellKey) {
    if (! preg_match('/^\d+-\d+$/', (string) $cellKey)) {
        return;
    }
    [$row, $col] = array_map('intval', explode('-', $cellKey));

    $entry = collect($this->layout)->firstWhere('ship', (int) $ship);
    if (! $entry) {
        return;
    }

    // the direction stays the same. placeShip validates the whole move
    $this->placeShip((int) $ship, $row, $col, $entry['dir']);
};

$fire = function ($row, $col) {
    $row = (int) $row;
    $col = (int) $col;

    if ($row < 0 || $row >= BattleshipRules::SIZE || $col < 0 || $col >= BattleshipRules::SIZE) {
        return;
    }

    $outcome = DB::transaction(function () use ($row, $col) {
        // lock this game's row: competing requests now go through one at a time
        $game = Game::where('code', $this->code)->lockForUpdate()->first();
        $me = $this->myPlayer();

        if (
            ! $game || ! $me
            || $game->status !== 'playing'
            || $game->turn_seat !== $me->seat
        ) {
            return null; // not allowed: ignore it quietly
        }

        $alreadyFired = $game->shots()
            ->where('seat', $me->seat)
            ->where('cell_row', $row)
            ->where('cell_col', $col)
            ->exists();
        if ($alreadyFired) {
            return null;
        }

        // the opponent's fleet is read on the SERVER only
        $opponent = $game->players()->where('seat', '!=', $me->seat)->first();
        $fleet = $opponent->fleet;

        $shipIndex = BattleshipRules::findShip($fleet, $row, $col);
        $result = $shipIndex === null ? 'miss' : 'hit';

        $game->shots()->create([
            'seat' => $me->seat,
            'cell_row' => $row,
            'cell_col' => $col,
            'result' => $result,
        ]);

        $message = $result === 'hit' ? 'Hit!' : 'Miss.';
        $won = false;

        if ($result === 'hit') {
            $hits = $game->shots()
                ->where('seat', $me->seat)
                ->where('result', 'hit')
                ->get()
                ->mapWithKeys(fn($s) => ["{$s->cell_row}-{$s->cell_col}" => true])
                ->all();

            $sunk = BattleshipRules::sunkShips($fleet, $hits);

            if (isset($sunk[$shipIndex])) {
                $message = 'You sank the ' . $fleet[$shipIndex]['name'] . '!';
            }
            $won = count($sunk) === count($fleet); // derived: every enemy ship is sunk
        }

        if ($won) {
            $game->update(['status' => 'finished', 'winner_seat' => $me->seat, 'turn_seat' => null]);
            $message = 'You sank the whole enemy fleet. You win!';
        } else {
            $game->update(['turn_seat' => $opponent->seat]);
        }

        return $message;
    });

    if ($outcome === null) {
        return;
    }

    $this->message = $outcome;

    // announce only AFTER the transaction has been saved
    broadcast(new RoomUpdated($this->code, 'shot'));
};


$requestRematch = function () {
    $me = $this->myPlayer();
    if (! $me) {
        return;
    }

    $newCode = DB::transaction(function () use ($me) {
        // lock the finished game, so two simultaneous presses can't create two rematches
        $game = Game::where('id', $me->game_id)->lockForUpdate()->first();

        if ($game->status !== 'finished') {
            return null;
        }
        if ($game->rematch_code) {
            return $game->rematch_code; // someone already asked: reuse it (idempotent)
        }

        $new = Game::create(['code' => Str::lower(Str::random(8)), 'status' => 'placing']);

        // same seats, same tokens: it is the same two people
        foreach ($game->players as $p) {
            $new->players()->create(['seat' => $p->seat, 'token' => $p->token]);
        }

        $game->update(['rematch_code' => $new->code]);

        return $new->code;
    });

    if (! $newCode) {
        return;
    }

    broadcast(new RoomUpdated($this->code, 'rematch'));

    return $this->redirect(route('battleship.rematch', $this->code));
};

$lockFleet = function () {
    $me = $this->myPlayer();
    if (! $me || $me->ready) {
        return;
    }

    // never trust the browser's layout: rebuild and re-check everything
    [$fleet, $errors] = BattleshipRules::fleetFromLayout($this->layout);
    if ($errors) {
        $this->message = implode(' ', $errors);
        return;
    }

    $this->message = '';
    $me->update(['fleet' => $fleet, 'ready' => true]);

    $game = $me->game;
    $started = false;

    if ($game->players()->where('ready', true)->count() === 2) {
        // atomic compare-and-set: only ONE request can flip placing -> playing
        $started = Game::where('id', $game->id)
            ->where('status', 'placing')
            ->update(['status' => 'playing', 'turn_seat' => 1]) === 1;
    }

    broadcast(new RoomUpdated($this->code, $started ? 'started' : 'ready'));
};

// calling this re-renders the component, which re-reads the database
$refreshRoom = function () {
    $this->heartbeat();
};

$heartbeat = function () {
    $me = $this->myPlayer();
    if ($me) {
        GamePlayer::whereKey($me->id)->update(['last_seen_at' => now()]);
    }
};
$claimVictory = function () {
    $won = DB::transaction(function () {
        $game = Game::where('code', $this->code)->lockForUpdate()->first();
        $me = $this->myPlayer();

        if (! $game || ! $me || $game->status !== 'playing') {
            return false;
        }

        // if it is MY turn I am not being kept waiting, so there is nothing to claim
        if ($game->turn_seat === $me->seat) {
            return false;
        }

        // the SERVER checks the clock, never the browser
        $opponent = $game->players()->where('seat', '!=', $me->seat)->first();
        if (
            ! $opponent || ! $opponent->last_seen_at
            || $opponent->last_seen_at->gt(now()->subSeconds(120))
        ) {
            return false;
        }

        $game->update([
            'status' => 'finished',
            'winner_seat' => $me->seat,
            'turn_seat' => null,
            'end_reason' => 'forfeit',
        ]);

        return true;
    });

    if ($won) {
        broadcast(new RoomUpdated($this->code, 'forfeit'));
    }
};

$pruneGame = function () {
    $game = Game::where('code', $this->code)->first();
    if ($game) {
        $code = $this->code;
        $game->delete();
        broadcast(new RoomUpdated($code, 'deleted'));
    }

    session()->forget("battleship.{$this->code}");

    return $this->redirect(route('battleship.lobby'));
};

with(function () {
    $game = Game::with(['players', 'shots'])->where('code', $this->code)->first();
    $me = $this->myPlayer();

    $view = [
        'game' => $game,
        'me' => $me,
        'myParts' => [],
        'mySunk' => [],          // indexes of MY ships that are sunk
        'incoming' => [],        // "row-col" => result: shots fired AT me
        'outgoing' => [],        // "row-col" => result: my shots at the enemy
        'sunkEnemyParts' => [],  // shapes of enemy ships that are fully hit, and ONLY those
        'lastKey' => null,
    ];

    if (! $me) {
        return $view;
    }

    $myFleet = $me->ready
        ? $me->fleet
        : BattleshipRules::fleetFromLayout($this->layout)[0];

    $view['myParts'] = $myFleet ? BattleshipRules::shipParts($myFleet) : [];

    $keyed = fn($shots) => $shots
        ->mapWithKeys(fn($s) => ["{$s->cell_row}-{$s->cell_col}" => $s->result])
        ->all();

    $mine = $game->shots->where('seat', $me->seat);
    $theirs = $game->shots->where('seat', '!=', $me->seat);

    $view['outgoing'] = $keyed($mine);
    $view['incoming'] = $keyed($theirs);

    // the opponent's latest shot is DERIVED from the log: the highest id
    $last = $theirs->sortByDesc('id')->first();
    $view['lastKey'] = $last ? "{$last->cell_row}-{$last->cell_col}" : null;

    if ($myFleet) {
        $theirHits = $keyed($theirs->where('result', 'hit'));
        $view['mySunk'] = array_keys(BattleshipRules::sunkShips($myFleet, $theirHits));
    }

    $opponent = $game->players->firstWhere('seat', '!=', $me->seat);
    if ($opponent && $opponent->fleet) {
        $myHits = $keyed($mine->where('result', 'hit'));
        $view['sunkEnemyParts'] = BattleshipRules::shipParts(
            BattleshipRules::sunkShips($opponent->fleet, $myHits)
        );
    }

    return $view;
});
?>

<div
    x-data
    wire:poll.15s="heartbeat"
    x-init="
        const refresh = () => $wire.refreshRoom();
        Echo.channel('game.{{ $code }}').listen('RoomUpdated', (e) => {
            console.log('RoomUpdated', e.reason);
            refresh();
        });
        Echo.connector.pusher.connection.bind('connected', refresh);
    "
    x-on:visibilitychange.window="if (! document.hidden) $wire.refreshRoom()"
    x-on:focus.window="$wire.refreshRoom()"
    class="max-w-3xl mx-auto p-4">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold">⚓ Battleship online</h1>
        <button wire:click="pruneGame"
            wire:confirm="Are you sure you want to leave and delete this game?"
            class="text-xs text-red-600 hover:text-red-700 hover:underline">
            🗑️ Leave & Prune
        </button>
    </div>

    @if (! $game)
    <p class="text-gray-700 mb-2">This game no longer exists.</p>
    <a href="{{ route('battleship.lobby') }}" class="text-blue-600 underline">Start a new game</a>
    @elseif (! $me)
    <p class="text-red-600 font-semibold">This game is already full.</p>
    @else
    @php
    $playing = $game->status === 'playing';
    $finished = $game->status === 'finished';
    $myTurn = $playing && $game->turn_seat === $me->seat;
    $editable = $game->status === 'placing' && ! $me->ready;
    @endphp

    <p class="text-sm text-gray-500 mb-3">
        You are <b>Player {{ $me->seat }}</b> · Game <code>{{ $code }}</code> · Status: <b>{{ $game->status }}</b>
    </p>

    <ul class="mb-4 space-y-1">
        @foreach ([1, 2] as $s)
        @php
        $p = $game->players->firstWhere('seat', $s);
        $online = $p && $p->last_seen_at && $p->last_seen_at->gt(now()->subSeconds(60));
        @endphp
        <li class="px-3 py-2 rounded {{ $p ? ($p->ready ? 'bg-green-100' : 'bg-yellow-50') : 'bg-gray-100 text-gray-500' }}">
            Player {{ $s }}:
            {{ $p ? ($p->ready ? 'ready ✓' : 'placing ships…') : 'waiting…' }}
            @if ($s === $me->seat) <span class="text-xs">(you)</span> @endif

            @if ($p && $s !== $me->seat && ! $finished)
            <span class="text-xs ml-2 {{ $online ? 'text-green-600' : 'text-gray-400' }}">
                {{ $online ? '● online' : '○ offline' . ($p->last_seen_at ? ' (last seen ' . $p->last_seen_at->diffForHumans() . ')' : '') }}
            </span>
            @endif
        </li>
        @endforeach
    </ul>

    @if ($game->players->count() < 2)
        <p class="mb-2">Send this link to your opponent:</p>
        <div class="flex gap-2 mb-3" x-data="{ copied: false }">
            <input readonly value="{{ route('battleship.room', $code) }}" class="flex-1 border rounded px-2 py-1 text-sm">
            <button type="button"
                x-on:click="navigator.clipboard.writeText('{{ route('battleship.room', $code) }}'); copied = true; setTimeout(() => copied = false, 1500)"
                class="px-3 py-1 bg-gray-200 rounded text-sm"
                x-text="copied ? 'Copied!' : 'Copy'"></button>
        </div>
        <div class="flex items-center justify-between mb-4">
            <p class="animate-pulse text-gray-500 text-sm">Waiting for your opponent…</p>
            <button wire:click="pruneGame"
                wire:confirm="Cancel and delete this game room?"
                class="text-xs text-red-600 hover:text-red-700 hover:underline">
                Cancel & Delete Room
            </button>
        </div>
        @endif

        @if ($playing)
        <p class="mb-2 font-semibold {{ $myTurn ? 'text-green-700' : 'text-gray-500 animate-pulse' }}">
            {{ $myTurn ? 'Your turn. Fire at the enemy waters!' : "Opponent's turn…" }}
        </p>
        @php
            $opponentPlayer = $game->players->firstWhere('seat', '!=', $me->seat);
            $opponentGone = ! $myTurn
                && $opponentPlayer
                && $opponentPlayer->last_seen_at
                && $opponentPlayer->last_seen_at->lt(now()->subSeconds(120));
        @endphp
        @if ($opponentGone)
        <div class="mb-3 p-3 bg-amber-50 border border-amber-300 rounded-lg flex items-center gap-3 flex-wrap">
            <span class="text-amber-700 text-sm font-medium">
                ⏳ Your opponent has been offline {{ $opponentPlayer->last_seen_at->diffForHumans() }}.
            </span>
            <button wire:click="claimVictory"
                class="ml-auto px-4 py-1.5 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white rounded-lg font-semibold text-sm transition-all shadow">
                🏆 Claim Victory
            </button>
        </div>
        @endif
        @elseif ($finished)
        @php $iWon = $game->winner_seat === $me->seat; @endphp
        <p class="mb-2 text-lg font-bold {{ $iWon ? 'text-green-600' : 'text-red-600' }}">
            @if ($game->end_reason === 'forfeit')
            {{ $iWon ? '🏳️ Your opponent left, so you win by forfeit.' : '⏱️ You were away too long, so the win went to your opponent.' }}
            @else
            {{ $iWon ? '🎉 You won!' : '💥 You lost. Your fleet was sunk.' }}
            @endif
        </p>

        <div class="flex items-center gap-3 flex-wrap mb-4">
            @if ($game->rematch_code)
            <a href="{{ route('battleship.rematch', $code) }}"
                class="px-4 py-2 bg-green-600 hover:bg-green-700 active:scale-95 text-white rounded font-semibold text-sm transition shadow">
                ⚔️ Opponent wants a rematch · Join!
            </a>
            @else
            <button wire:click="requestRematch" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white rounded font-semibold text-sm transition shadow">
                🔁 Rematch
            </button>
            @endif

            <button wire:click="pruneGame"
                wire:confirm="Are you sure you want to delete this game and leave?"
                class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded font-semibold text-sm transition shadow-sm">
                🗑️ Prune Game & Leave
            </button>
        </div>
        @endif

        @if ($message && ! $myTurn)
        <p class="mb-2 text-sm {{ $game->status === 'placing' ? 'text-red-600' : 'text-gray-700' }}">{{ $message }}</p>
        @endif

        <div class="flex flex-col lg:flex-row gap-6 items-center lg:items-start justify-center mb-4">

            {{-- ENEMY WATERS --}}
            @if ($playing || $finished)
            <div class="flex flex-col items-center">
                <h2 class="font-semibold mb-1">Enemy waters</h2>
                <div class="inline-grid gap-0 p-1 rounded-lg bg-slate-700"
                    style="grid-template-columns: repeat(10, minmax(0, 1fr));">
                    @for ($r = 0; $r < 10; $r++)
                        @for ($c=0; $c < 10; $c++)
                        @php
                        $key="{$r}-{$c}" ;
                        $part=$sunkEnemyParts[$key] ?? null;
                        $res=$outgoing[$key] ?? null;

                        if ($part) {
                        $bg='water fx-cell' ;
                        } elseif ($res==='hit' ) {
                        $bg='scorch fx-cell' ;
                        } elseif ($res==='miss' ) {
                        $bg='water fx-cell' ;
                        } else {
                        $bg='fog ' . ($myTurn ? 'cursor-pointer hover:brightness-150' : 'cursor-not-allowed' );
                        }
                        @endphp
                        <div wire:key="en-{{ $key }}"
                        @if ($myTurn && ! $res) wire:click="fire({{ $r }}, {{ $c }})" @endif
                        @if ($part) style="--d: {{ $part['i'] * 0.12 }}s" @endif
                        class="w-7 h-7 sm:w-8 sm:h-8 select-none {{ $bg }}">
                        @if ($part)
                        <span class="hull hull-{{ $part['dir'] }} {{ $part['part'] }} ship-{{ $part['ship'] }} wreck {{ $part['cabin'] ? 'cabin' : '' }}"></span>
                        <span class="boom boom-big"></span>
                        <span class="smoke"></span>
                        @elseif ($res === 'hit')
                        <span class="boom"></span>
                        <span class="fire">🔥</span>
                        @elseif ($res === 'miss')
                        <span class="splash"></span>
                        <span class="foam"></span>
                        @endif
                </div>
                @endfor
                @endfor
            </div>
        </div>
        @endif

        {{-- YOUR FLEET --}}
        <div class="flex flex-col items-center">
            <h2 class="font-semibold mb-1">Your fleet</h2>
            <div class="inline-grid gap-0 p-1 rounded-lg bg-slate-700"
                style="grid-template-columns: repeat(10, minmax(0, 1fr));">
                @for ($r = 0; $r < 10; $r++)
                    @for ($c=0; $c < 10; $c++)
                    @php
                    $key="{$r}-{$c}" ;
                    $part=$myParts[$key] ?? null;
                    $inc=$incoming[$key] ?? null;
                    $isSunk=$part && in_array($part['ship'], $mySunk, true);
                    @endphp

                    @if ($editable)
                    {{-- placing: every cell is a one-slot list, and the ship bar sits in its first cell --}}
                    <div wire:key="cell-{{ $key }}"
                    wire:sort="dropShip" wire:sort:group="fleet" wire:sort:group-id="{{ $key }}"
                    class="water relative w-7 h-7 sm:w-8 sm:h-8">
                    @if ($part && $part['i'] === 0)
                    @php
                    $len = BattleshipRules::FLEET[$part['ship']]['length'];
                    $h = $part['dir'] === 'h';
                    @endphp
                    <div wire:key="ship-{{ $part['ship'] }}"
                        wire:sort:item="{{ $part['ship'] }}"
                        class="absolute left-0 top-0 z-20 flex {{ $h ? 'flex-row' : 'flex-col' }} select-none shadow-md group"
                        style="width: {{ $h ? $len * 100 : 100 }}%; height: {{ $h ? 100 : $len * 100 }}%;">
                        @for ($k = 0; $k < $len; $k++)
                            @php
                                $segPart = $k === 0 ? 'stern' : ($k === $len - 1 ? 'bow' : 'mid');
                                $segCabin = $k > 0 && $k < $len - 1 && $k === intdiv($len - 1, 2);
                            @endphp
                            <div class="relative w-7 h-7 sm:w-8 sm:h-8 flex-shrink-0"
                                wire:click="rotateShip({{ $part['ship'] }})"
                                title="Click to rotate {{ BattleshipRules::FLEET[$part['ship']]['name'] }}">
                                <span class="hull hull-{{ $h ? 'h' : 'v' }} {{ $segPart }} ship-{{ $part['ship'] }} {{ $segCabin ? 'cabin' : '' }} group-hover:brightness-110 transition"></span>
                                @if ($k === 0)
                                    <div wire:sort:handle title="Drag to move, click to rotate"
                                        class="absolute inset-0 z-30 flex items-center justify-center cursor-grab active:cursor-grabbing text-white/90 text-xs select-none bg-black/20 hover:bg-black/35 rounded transition">
                                        ⠿
                                    </div>
                                @else
                                    <div class="absolute inset-0 z-20 flex items-center justify-center opacity-0 group-hover:opacity-80 transition text-[10px] text-white pointer-events-none">
                                        ↻
                                    </div>
                                @endif
                            </div>
                        @endfor
                    </div>
                    @endif
            </div>
            @else
            {{-- after Ready and during the battle: the normal cell --}}
            <div wire:key="my-{{ $key }}"
                @if ($isSunk) style="--d: {{ $part['i'] * 0.12 }}s" @endif
                class="water w-7 h-7 sm:w-8 sm:h-8 {{ $inc ? 'fx-cell' : '' }} {{ $lastKey === $key ? 'last-shot' : '' }}">
                @if ($part)
                <span class="hull hull-{{ $part['dir'] }} {{ $part['part'] }} ship-{{ $part['ship'] }} {{ $part['cabin'] ? 'cabin' : '' }} {{ $isSunk ? 'wreck' : ($inc ? 'hit' : '') }}"></span>
                @if ($isSunk)
                <span class="boom boom-big"></span>
                <span class="smoke"></span>
                @elseif ($inc === 'hit')
                <span class="boom"></span>
                <span class="fire">🔥</span>
                @endif
                @elseif ($inc === 'miss')
                <span class="splash"></span>
                <span class="foam"></span>
                @endif
            </div>
            @endif
            @endfor
            @endfor
        </div>

        @if ($editable)
        <div class="mt-4 flex flex-col items-center">
            <p class="text-xs text-slate-500 mb-2 font-medium">Click any ship or button to rotate ↻ · Drag ⠿ to reposition</p>
            <div class="flex flex-wrap gap-2.5 justify-center max-w-xl">
                @foreach ($layout as $entry)
                @php
                    $shipId = $entry['ship'];
                    $shipDef = BattleshipRules::FLEET[$shipId];
                    $len = $shipDef['length'];
                    $isH = $entry['dir'] === 'h';
                @endphp
                <button wire:click="rotateShip({{ $shipId }})"
                    title="Rotate {{ $shipDef['name'] }}"
                    class="flex items-center gap-2 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 active:scale-95 text-white rounded-lg text-xs font-medium transition shadow border border-slate-700">
                    <span class="text-sky-400 font-bold text-sm">↻</span>
                    <span class="font-semibold text-slate-100">{{ $shipDef['name'] }}</span>
                    <div class="inline-flex items-center gap-0 p-0.5 rounded bg-slate-950 border border-slate-700">
                        @for ($i = 0; $i < $len; $i++)
                            @php
                                $part = $i === 0 ? 'stern' : ($i === $len - 1 ? 'bow' : 'mid');
                                $cabin = $i > 0 && $i < $len - 1 && $i === intdiv($len - 1, 2);
                            @endphp
                            <div class="water relative w-3.5 h-3.5 select-none">
                                <span class="hull hull-h {{ $part }} ship-{{ $shipId }} {{ $cabin ? 'cabin' : '' }}"></span>
                            </div>
                        @endfor
                    </div>
                    <span class="text-[10px] text-slate-400 font-mono">({{ $isH ? '↔ H' : '↕ V' }})</span>
                </button>
                @endforeach
            </div>
        </div>
        @endif
</div>
</div>
{{-- PLACING CONTROLS --}}
@if ($game->status === 'placing')
@if (! $me->ready)
<div class="flex gap-2 justify-center">
    <button wire:click="reroll" class="px-4 py-2 bg-gray-200 rounded">🎲 Re-roll</button>
    <button wire:click="lockFleet" class="px-4 py-2 bg-blue-600 text-white rounded font-semibold">Ready ✓</button>
</div>
@else
<p class="text-gray-600 text-center">You are ready ✓. Waiting for your opponent to finish placing…</p>
@endif
@endif

{{-- REJOIN LINK: available to a seated player in every phase --}}
<details class="mt-6 text-sm text-gray-600">
    <summary class="cursor-pointer">Lost your seat? Keep your private rejoin link</summary>
    <p class="mt-1">
        Open this on another device, or after clearing cookies, to take your seat back.
        <b>Anyone with this link can play as you, so don't share it.</b>
    </p>
    <input readonly value="{{ route('battleship.rejoin', [$code, $me->token]) }}"
        class="w-full border rounded px-2 py-1 text-xs mt-1">
</details>
@endif
</div>