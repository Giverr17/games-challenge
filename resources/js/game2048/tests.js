import { slideRow, move, spawnTile, emptyGrid, canMove, statusOf, play } from './rules';

const eq = (a, b) => JSON.stringify(a) === JSON.stringify(b);
const sum = g => g.flat().reduce((a, b) => a + b, 0);

const stuck = [[2, 4, 8, 16], [16, 8, 4, 2], [2, 4, 8, 16], [16, 8, 4, 2]];
const column = [[2, 0, 0, 0], [2, 0, 0, 0], [4, 0, 0, 0], [0, 0, 0, 0]];
const blank = () => emptyGrid();

// Each test is [name, function that returns true when it passes]
export const tests = [
    ['slideRow [2,0,2,4] gives [4,4,0,0], +4',
        () => eq(slideRow([2, 0, 2, 4]), { row: [4, 4, 0, 0], gained: 4 })],
    ['slideRow [2,2,2,2] gives [4,4,0,0], +8',
        () => eq(slideRow([2, 2, 2, 2]), { row: [4, 4, 0, 0], gained: 8 })],
    ['slideRow [4,2,2,0] gives [4,4,0,0] (the trap)',
        () => eq(slideRow([4, 2, 2, 0]), { row: [4, 4, 0, 0], gained: 4 })],
    ['slideRow [2,4,8,16] stays the same, +0',
        () => eq(slideRow([2, 4, 8, 16]), { row: [2, 4, 8, 16], gained: 0 })],
    ['move right: [0,2,2,4] gives [0,0,4,4]',
        () => eq(move([[0, 2, 2, 4], ...blank().slice(1)], 'right').grid[0], [0, 0, 4, 4])],
    ['move never edits the original grid',
        () => {
            const g = [[0, 2, 2, 4], ...blank().slice(1)];
            move(g, 'right');
            return eq(g[0], [0, 2, 2, 4]);
        }],
    ['up and down move whole columns',
        () => eq(move(column, 'up').grid.map(r => r[0]), [4, 4, 0, 0])
            && eq(move(column, 'down').grid.map(r => r[0]), [0, 0, 4, 4])],
    ['merging never changes the total',
        () => sum(column) === sum(move(column, 'up').grid)],
    ['spawn picks the first empty cell and a 2',
        () => spawnTile(blank(), () => 0)[0][0] === 2],
    ['spawn picks the last empty cell and a 4',
        () => spawnTile(blank(), () => 0.95)[3][3] === 4],
    ['stuck board: no move, game over',
        () => !move(stuck, 'left').moved && !canMove(stuck) && statusOf(stuck) === 'over'],
    ['stuck board: play returns the very same state',
        () => {
            const s = { grid: stuck, score: 5 };
            return play(s, 'left') === s;
        }],
    ['full turn: slide, score, spawn',
        () => {
            const t = play({ grid: [[0, 2, 2, 4], ...blank().slice(1)], score: 0 }, 'right', () => 0);
            return eq(t.grid[0], [2, 0, 4, 4]) && t.score === 4;
        }],
    ['a 2048 tile means won',
        () => statusOf([[2048, 0, 0, 0], ...blank().slice(1)]) === 'won'],
];