import { tests } from './tests';

export default function TestPanel() {
    const results = tests.map(([name, fn]) => {
        try {
            return { name, ok: fn() === true };
        } catch {
            return { name, ok: false };
        }
    });
    const passed = results.filter(r => r.ok).length;

    return (
        <div className="mt-8 rounded border border-stone-300 bg-white p-4 text-sm">
            <p className="mb-2 font-semibold">
                Rules self-test: {passed} / {results.length} passed
            </p>
            <ul className="space-y-1">
                {results.map(r => (
                    <li key={r.name} className={r.ok ? 'text-green-700' : 'text-red-600'}>
                        {r.ok ? '✓' : '✗'} {r.name}
                    </li>
                ))}
            </ul>
        </div>
    );
}