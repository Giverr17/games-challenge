import { useState, useEffect, useRef } from 'react';
import { newGame, tick, turn, speedFor } from './rules';

const MIN_SWIPE = 30; // pixels. Shorter than this counts as a tap.
const BEST_KEY = 'snake-best';

const KEYS = {
    ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right',
    w: 'up', s: 'down', a: 'left', d: 'right',
};

// Where the two eyes sit on the head, as [left%, top%], for each direction
const EYES = {
    right: [[58, 14], [58, 64]],
    left: [[20, 14], [20, 64]],
    up: [[14, 20], [64, 20]],
    down: [[14, 58], [64, 58]],
};

// localStorage can throw (private mode, blocked storage), so wrap it
function readBest() {
    try {
        return Number(localStorage.getItem(BEST_KEY)) || 0;
    } catch {
        return 0;
    }
}

function saveBest(n) {
    try {
        localStorage.setItem(BEST_KEY, String(n));
    } catch {
        // nothing to do, the best score just won't be remembered
    }
}

// One steering request. If the game is waiting to start, any steer starts it.
function steerState(old, dir) {
    const s = old.status === 'ready' ? { ...old, status: 'playing' } : old;
    return turn(s, dir);
}

// playing <-> paused. Every other status is left alone.
function togglePause(old) {
    if (old.status === 'playing') return { ...old, status: 'paused' };
    if (old.status === 'paused') return { ...old, status: 'playing' };
    return old;
}

// The one big button / Space key: start, pause, resume, or play again
function primaryAction(old) {
    if (old.status === 'ready') return { ...old, status: 'playing' };
    if (old.status === 'over' || old.status === 'won') {
        return { ...newGame(), status: 'playing' };
    }
    return togglePause(old);
}

// A finger movement (dx = columns, dy = rows) becomes a direction, or null for a tap
function swipeDir(dx, dy) {
    if (Math.abs(dx) < MIN_SWIPE && Math.abs(dy) < MIN_SWIPE) return null;
    if (Math.abs(dx) > Math.abs(dy)) return dx > 0 ? 'right' : 'left';
    return dy > 0 ? 'down' : 'up';
}

export default function SnakeApp() {
    const [game, setGame] = useState(() => newGame());
    const [best, setBest] = useState(readBest);
    const touchStart = useRef(null);
    const boardRef = useRef(null);
    // The delay is derived from the score, never stored
    const delay = speedFor(game.score);

    // The loop: runs only while playing. A new delay means a new timer.
    useEffect(() => {
        if (game.status !== 'playing') return;

        const id = setInterval(() => {
            setGame(old => tick(old));
        }, delay);

        return () => clearInterval(id);
    }, [game.status, delay]);

    // Keyboard: steering plus pause
    useEffect(() => {
        function onKey(e) {
            const key = e.key;

            if (key === ' ' || key.toLowerCase() === 'p') {
                e.preventDefault();
                if (e.repeat) return;
                setGame(key === ' ' ? primaryAction : togglePause);
                return;
            }

            const dir = KEYS[key] || KEYS[key.toLowerCase()];
            if (!dir) return;
            e.preventDefault();
            setGame(old => steerState(old, dir));
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Auto-pause when the tab or app is hidden
    useEffect(() => {
        function onHide() {
            if (document.hidden) {
                setGame(old => (old.status === 'playing' ? { ...old, status: 'paused' } : old));
            }
        }
        document.addEventListener('visibilitychange', onHide);
        return () => document.removeEventListener('visibilitychange', onHide);
    }, []);

    // High score: when a game ends, keep the better number
    useEffect(() => {
        const ended = game.status === 'over' || game.status === 'won';
        if (ended && game.score > best) {
            setBest(game.score);
            saveBest(game.score);
        }
    }, [game.status, game.score, best]);

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
        if (dir) {
            setGame(old => steerState(old, dir));
        } else if (boardRef.current && boardRef.current.contains(e.target)) {
            setGame(togglePause); // a tap on the board pauses or resumes
        }
    }

    // Buttons give up keyboard focus, so pressing Space later doesn't click them twice
    const click = fn => e => {
        e.currentTarget.blur();
        fn();
    };

    const restart = () => setGame({ ...newGame(), status: 'playing' });

    // The drawing: for every cell, decide what to put in it
    const head = game.snake[0];
    const eyes = EYES[game.dir] || EYES.right;
    const cells = [];
    for (let y = 0; y < game.rows; y++) {
        for (let x = 0; x < game.cols; x++) {
            let piece = null;
            if (head.x === x && head.y === y) {
                piece = (
                    <div className="absolute inset-[6%] rounded-sm bg-[#1a2410]">
                        {eyes.map(([left, top]) => (
                            <div
                                key={`${left}-${top}`}
                                className="absolute h-[22%] w-[22%] rounded-full bg-[#c7d99b]"
                                style={{ left: `${left}%`, top: `${top}%` }}
                            />
                        ))}
                    </div>
                );
            } else if (game.snake.some(seg => seg.x === x && seg.y === y)) {
                piece = <div className="absolute inset-[6%] rounded-sm bg-[#2f3d1f]" />;
            } else if (game.food && game.food.x === x && game.food.y === y) {
                piece = <div className="absolute inset-[20%] rounded-full bg-[#2f3d1f]" />;
            }
            cells.push(
                <div key={`${x}-${y}`} className="relative aspect-square bg-[#c7d99b]">
                    {piece}
                </div>
            );
        }
    }

    const overlayText = {
        ready: 'Swipe or press an arrow to start',
        paused: 'Paused',
        over: 'Game over',
        won: 'You won!',
    }[game.status];

    const mainLabel = {
        ready: 'Start',
        playing: 'Pause',
        paused: 'Resume',
        over: 'Play again',
        won: 'Play again',
    }[game.status];

    return (
        <div
            className="min-h-dvh select-none bg-[#9db36f] text-[#1a2410]"
            style={{ touchAction: 'none' }}
            onTouchStart={onTouchStart}
            onTouchEnd={onTouchEnd}
        >
            <div className="mx-auto max-w-md p-4">
                <div className="mb-3 flex items-center justify-between font-semibold">
                    <p className="text-lg">Score: {game.score}</p>
                    <p className="text-lg">Best: {best}</p>
                </div>

                <div ref={boardRef} className="relative border-4 border-[#2f3d1f]">
                    <div
                        className="grid"
                        style={{ gridTemplateColumns: `repeat(${game.cols}, 1fr)` }}
                    >
                        {cells}
                    </div>

                    {overlayText && (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-[#c7d99b]/85 text-center">
                            <p className="text-2xl font-bold">{overlayText}</p>
                            {(game.status === 'over' || game.status === 'won') && (
                                <p>Score: {game.score}</p>
                            )}
                        </div>
                    )}
                </div>

                <div className="mt-4 flex gap-3">
                    <button
                        onClick={click(() => setGame(primaryAction))}
                        className="rounded bg-[#2f3d1f] px-4 py-2 text-[#c7d99b]"
                    >
                        {mainLabel}
                    </button>
                    <button
                        onClick={click(restart)}
                        className="rounded border-2 border-[#2f3d1f] px-4 py-2"
                    >
                        Restart
                    </button>
                </div>
            </div>
        </div>
    );
}
