import { useState, useEffect, useRef } from 'react';
import { newGame, tick, turn } from './rules';

const TICK_MS = 150;
const MIN_SWIPE = 30; // pixels. Shorter than this counts as a tap.

const KEYS = {
    ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right',
    w: 'up', s: 'down', a: 'left', d: 'right',
};

// One steering request. If the game is waiting to start, any steer starts it.
function steerState(old, dir) {
    const s = old.status === 'ready' ? { ...old, status: 'playing' } : old;
    return turn(s, dir);
}

// A finger movement (dx = columns, dy = rows) becomes a direction, or null for a tap
function swipeDir(dx, dy) {
    if (Math.abs(dx) < MIN_SWIPE && Math.abs(dy) < MIN_SWIPE) return null;
    if (Math.abs(dx) > Math.abs(dy)) return dx > 0 ? 'right' : 'left';
    return dy > 0 ? 'down' : 'up';
}

export default function SnakeApp() {
    const [game, setGame] = useState(() => newGame());
    const touchStart = useRef(null);

    // The loop: runs only while the game is playing
    useEffect(() => {
        if (game.status !== 'playing') return;

        const id = setInterval(() => {
            setGame(old => tick(old));
        }, TICK_MS);

        return () => clearInterval(id);
    }, [game.status]);

    // Keyboard: one listener for the whole page, removed when we leave
    useEffect(() => {
        function onKey(e) {
            const dir = KEYS[e.key] || KEYS[e.key.toLowerCase()];
            if (!dir) return;
            e.preventDefault(); // stop arrow keys from scrolling the page
            setGame(old => steerState(old, dir));
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Swipe: remember where the finger landed, compare where it lifted
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
        if (dir) setGame(old => steerState(old, dir));
    }

    function start() {
        setGame(old => ({ ...old, status: 'playing' }));
    }

    function restart() {
        setGame({ ...newGame(), status: 'playing' });
    }

    // The drawing: for every cell, decide its colour
    const head = game.snake[0];
    const cells = [];
    for (let y = 0; y < game.rows; y++) {
        for (let x = 0; x < game.cols; x++) {
            let color = 'bg-gray-100';
            if (head.x === x && head.y === y) {
                color = 'bg-green-800';
            } else if (game.snake.some(seg => seg.x === x && seg.y === y)) {
                color = 'bg-green-500';
            } else if (game.food && game.food.x === x && game.food.y === y) {
                color = 'bg-red-500';
            }
            cells.push(<div key={`${x}-${y}`} className={`aspect-square ${color}`} />);
        }
    }

    const message = {
        ready: 'Press Start, swipe or use arrow keys',
        playing: 'Go!',
        paused: 'Paused',
        over: 'Game over',
        won: 'You won!',
    }[game.status];

    return (
        <div
            className="min-h-dvh select-none"
            style={{ touchAction: 'none' }}
            onTouchStart={onTouchStart}
            onTouchEnd={onTouchEnd}
        >
            <div className="mx-auto max-w-md p-4">
                <div className="mb-3 flex items-center justify-between">
                    <p className="text-lg font-semibold">Score: {game.score}</p>
                    <p className="text-right text-gray-600">{message}</p>
                </div>

                <div
                    className="grid gap-px border border-gray-300 bg-gray-300"
                    style={{ gridTemplateColumns: `repeat(${game.cols}, 1fr)` }}
                >
                    {cells}
                </div>

                <div className="mt-4 flex gap-3">
                    {game.status === 'ready' && (
                        <button onClick={start} className="rounded bg-green-600 px-4 py-2 text-white">
                            Start
                        </button>
                    )}
                    <button onClick={restart} className="rounded bg-gray-800 px-4 py-2 text-white">
                        Restart
                    </button>
                </div>
            </div>
        </div>
    );
}