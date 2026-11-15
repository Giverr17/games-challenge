export const COLS = 16;
export const ROWS = 16;

export const DIRS = {
    up: { x: 0, y: -1 },
    down: { x: 0, y: 1 },
    left: { x: -1, y: 0 },
    right: { x: 1, y: 0 },
};

const same = (a, b) => a.x === b.x && a.y === b.y;

export function freeCells(snake, cols = COLS, rows = ROWS) {
    const free = [];
    for (let y = 0; y < rows; y++) {
        for (let x = 0; x < cols; x++) {
            if (!snake.some(seg => seg.x === x && seg.y === y)) {
                free.push({ x, y });
            }
        }
    }
    return free;
}

// Pick a random free cell, or null if the board is full
export function placeFood(snake, cols = COLS, rows = ROWS) {
    const free = freeCells(snake, cols, rows);
    if (free.length === 0) return null;
    return free[Math.floor(Math.random() * free.length)];
}

export function newGame(cols = COLS, rows = ROWS) {
    const y = Math.floor(rows / 2);
    const snake = [
        { x: 2, y },
        { x: 1, y },
        { x: 0, y },
    ];
    return {
        cols,
        rows,
        snake,
        dir: 'right',
        nextDirs: [],
        food: placeFood(snake, cols, rows),
        score: 0,
        status: 'ready',   // ready | playing | paused | over | won
    };
}

export function turn(state, newDir) {
    if (!DIRS[newDir]) return state;

    const queue = Array.isArray(state.nextDirs) ? state.nextDirs : [];
    if (queue.length >= 2) return state; // buffer up to 2 future turns

    const last = queue.length > 0 ? queue[queue.length - 1] : state.dir;
    const cur = DIRS[last];
    const next = DIRS[newDir];
    const isReverse = cur.x + next.x === 0 && cur.y + next.y === 0;

    if (isReverse || last === newDir) return state;

    return {
        ...state,
        nextDirs: [...queue, newDir],
    };
}

export function tick(state) {
    if (state.status !== 'playing') return state;

    const queue = Array.isArray(state.nextDirs) ? [...state.nextDirs] : [];
    const dir = queue.length > 0 ? queue.shift() : state.dir;

    const { snake, food, cols, rows } = state;
    const d = DIRS[dir];
    const head = snake[0];

    // new head
    const newHead = { x: head.x + d.x, y: head.y + d.y };

    // wall check
    if (newHead.x < 0 || newHead.x >= cols || newHead.y < 0 || newHead.y >= rows) {
        return { ...state, dir, nextDirs: queue, status: 'over' };
    }

    // does the new head land on food?
    const grows = food !== null && same(newHead, food);

    // self check. If not growing, the tail leaves this tick, so skip it.
    const body = grows ? snake : snake.slice(0, -1);
    if (body.some(seg => same(seg, newHead))) {
        return { ...state, dir, nextDirs: queue, status: 'over' };
    }

    // 5) build the new snake: add head, drop tail unless growing
    const newSnake = [newHead, ...(grows ? snake : snake.slice(0, -1))];

    if (!grows) {
        return { ...state, dir, nextDirs: queue, snake: newSnake };
    }
    
    // 6) ate food: new food on a free cell, or win if none left
    const newFood = placeFood(newSnake, cols, rows);
    return {
        ...state,
        dir,
        nextDirs: queue,
        snake: newSnake,
        food: newFood,
        score: state.score + 1,
        status: newFood === null ? 'won' : 'playing',
    };


}
// Speed ramp: derived from the score, never stored
export const BASE_MS = 150;
export const STEP_MS = 5;
export const MIN_MS = 70;

export function speedFor(score) {
return Math.max(MIN_MS, BASE_MS - score * STEP_MS);
}
