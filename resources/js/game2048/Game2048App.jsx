import { useState, useEffect, useRef } from 'react';
import { newGame, play, statusOf } from './rules';
import TestPanel from './TestPanel';

const KEYS = {
    ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right',
    w: 'up', s: 'down', a: 'left', d: 'right',
};

const MIN_SWIPE = 30; // pixels. Shorter than this counts as a tap.


function tryMove(old, dir) {
    return statusOf(old.grid) === 'playing' ? play(old, dir) : old;
}


// value -> Tailwind classes. Written out in full so Tailwind can find them.
const TILE = {
    0: 'bg-stone-200',
    2: 'bg-amber-100 text-stone-700',
    4: 'bg-amber-200 text-stone-700',
    8: 'bg-orange-300 text-white',
    16: 'bg-orange-400 text-white',
    32: 'bg-orange-500 text-white',
    64: 'bg-red-500 text-white',
    128: 'bg-yellow-400 text-white',
    256: 'bg-yellow-500 text-white',
    512: 'bg-lime-500 text-white',
    1024: 'bg-emerald-500 text-white',
    2048: 'bg-teal-500 text-white',
};

function swipeDir(dx, dy) {
    if (Math.abs(dx) < MIN_SWIPE && Math.abs(dy) < MIN_SWIPE) return null;
    if (Math.abs(dx) > Math.abs(dy)) return dx > 0 ? 'right' : 'left';
    return dy > 0 ? 'down' : 'up';
}

export default function Game2048App() {
    const [game, setGame] = useState(() => newGame());
    const touchStart = useRef(null);

    useEffect(() => {
        function onKey(e) {
            const dir = KEYS[e.key] || KEYS[e.key.toLowerCase()];
            if (!dir) return;
            e.preventDefault();
            if (e.repeat) return;
            setGame(old => tryMove(old, dir));
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    function onTouchStart(e) {
        const t = e.touches[0];
        touchStart.current = { x: t.clientX, y: t.clientY };
    }

    function onTouchEnd(e) {
        if (!touchStart.current) return;
        const t = e.changedTouches[0];
        const dir = swipeDir(
            t.clientX - touchStart.current.x,
            t.clientY - touchStart.current.y
        );
        touchStart.current = null;
        if (dir) setGame(old => tryMove(old, dir));
    }

    const status = statusOf(game.grid);

    const cells = [];
    for (let r = 0; r < game.grid.length; r++) {
        for (let c = 0; c < game.grid[r].length; c++) {
            const v = game.grid[r][c];
            const size = v >= 1000 ? 'text-xl' : 'text-2xl';
            const look = TILE[v] ?? 'bg-stone-800 text-white';
            cells.push(
                <div
                    key={`${r}-${c}`}
                    className={`flex aspect-square items-center justify-center rounded font-bold ${size} ${look}`}
                >
                    {v === 0 ? '' : v}
                </div>
            );
        }
    }

    const showTests = new URLSearchParams(window.location.search).has('test');

     return (
        <div
            className="min-h-dvh select-none bg-stone-100 p-4 text-stone-800"
            style={{ touchAction: 'none' }}
            onTouchStart={onTouchStart}
            onTouchEnd={onTouchEnd}
        >
            <div className="mx-auto max-w-md">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-4xl font-bold">2048</h1>
                    <div className="rounded bg-stone-300 px-4 py-2 text-center">
                        <p className="text-xs uppercase tracking-wide">Score</p>
                        <p className="text-xl font-bold">{game.score}</p>
                    </div>
                </div>

                <div className="relative rounded-lg bg-stone-400 p-2">
                    <div
                        className="grid gap-2"
                        style={{ gridTemplateColumns: `repeat(${game.grid.length}, 1fr)` }}
                    >
                        {cells}
                    </div>

                    {status !== 'playing' && (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-lg bg-white/80 text-center">
                            <p className="text-3xl font-bold">
                                {status === 'won' ? 'You win!' : 'Game over'}
                            </p>
                            <button
                                onClick={() => setGame(newGame())}
                                className="rounded bg-stone-800 px-4 py-2 text-white"
                            >
                                Try again
                            </button>
                        </div>
                    )}
                </div>

                <div className="mt-4 flex items-center justify-between gap-3">
                    <p className="text-sm text-stone-600">Swipe, or use arrow keys / W A S D.</p>
                    <button
                        onClick={() => setGame(newGame())}
                        className="shrink-0 rounded bg-stone-800 px-4 py-2 text-white"
                    >
                        New game
                    </button>
                </div>

                {showTests && <TestPanel />}
            </div>
        </div>
    );
}