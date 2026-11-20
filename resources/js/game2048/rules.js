export const SIZE = 4;
export const WIN_TILE = 2048;

const DIRECTIONS = ['up', 'down', 'left', 'right'];

export function emptyGrid(size = SIZE) {
    return Array.from({ length: size }, () =>  Array(size).fill(0) );
}

export function slideRow(row) {
    const packed = row.filter(v => v !== 0);

    const merged = [];
    let gained = 0;
    let i = 0;

    while (i < packed.length) {
        if (i + 1 < packed.length && packed[i] === packed[i + 1]) {
            const value = packed[i] * 2;
            merged.push(value);
            gained += value
            i += 2;
        } else {
            merged.push(packed[i]);
            i += 1;
        }
    }

    while (merged.length < row.length) merged.push(0);
    return { row: merged, gained };
}

const reverse = row => [...row].reverse();

function transpose(grid) {
    return grid[0].map((_, c) => grid.map(row => row[c]));
}

function sameGrid(a, b) {
    return a.every((row, r) => row.every((v, c) => v === b[r][c]));
}

function moveLeft(grid) {
    let gained = 0;
    const out = grid.map((row) => {
        const result = slideRow(row);
        gained += result.gained;
        return result.row;
    }
    );
    return { grid: out, gained };
}

export function move(grid, dir) {
    if (!DIRECTIONS.includes(dir)) return { grid, gained: 0, moved: false }

    const vertical = dir === 'up' || dir === 'down';
    const flipped = dir === 'right' || dir === 'down';

    let g = grid;

    if (vertical) g = transpose(g);
    if (flipped) g = g.map(reverse);

    const result = moveLeft(g);
    g = result.grid;

    if (flipped) g = g.map(reverse);
    if (vertical) g = transpose(g);

    return { grid: g, gained: result.gained, moved: !sameGrid(grid, g) };
}

export function emptyCells(grid) {
    const cells = [];

    for (let r = 0; r < grid.length; r++) {
        for (let c = 0; c < grid[0].length; c++) {
            if (grid[r][c] === 0) cells.push({ r, c });
        }
    }

    return cells;
}

export function spawnTile   (grid, rand = Math.random) {
    const empty = emptyCells(grid);
    if (empty.length === 0) return grid;

    const { r, c } = empty[Math.floor(rand() * empty.length)];
    const value = rand() < 0.9 ? 2 : 4;

    return grid.map((row,ri)=>
        row.map((v,ci)=>(ri===r && ci===c ? value : v))
    )
}

export function canMove(grid) {
    return DIRECTIONS.some(dir => move(grid, dir).moved);
}

export function hasWon(grid) {
    return grid.some(row => row.some(v => v >= WIN_TILE));
}


export function statusOf(grid) {
    if (hasWon(grid)) return 'won';
    if (!canMove(grid)) return 'over';
    return 'playing';
}

export function newGame(rand = Math.random) {
    let grid = emptyGrid();
    grid = spawnTile(grid, rand);
    grid = spawnTile(grid, rand);
    return { grid, score: 0 };
}

export function play(state, dir, rand = Math.random) {
    const result = move(state.grid, dir);
    if (!result.moved) return state;

    return {
        grid: spawnTile(result.grid, rand),
        score: state.score + result.gained,
    };
}