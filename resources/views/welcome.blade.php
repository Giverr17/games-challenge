<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Games challenge') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])


</head>

<body>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 p-8">
        <a href="{{ route('minesweeper') }}"
            class="block rounded-xl border border-gray-200 p-6 hover:shadow-lg transition">
            <h2 class="text-xl font-semibold">🧩 Minesweeper</h2>
            <p class="text-gray-500 mt-2">Flood-fill, graphs, and a little constraint logic.</p>
        </a>
        <a href="{{ route('memory-match') }}"
            class="block rounded-xl border border-gray-200 p-6 hover:shadow-lg transition">
            <h2 class="text-xl font-semibold">🧠 Memory Match</h2>
            <p class="text-gray-500 mt-2">Flip cards to find matching pairs. Test your short-term memory.</p>
        </a>
        <a href="{{ route('mastermind') }}"
            class="block rounded-xl border border-gray-200 p-6 hover:shadow-lg transition">
            <h2 class="text-xl font-semibold">🎲 Mastermind</h2>
            <p class="text-gray-500 mt-2">Guess the secret code with colored pegs. Deduction and logic.</p>
        </a>
        <a href="{{ route('wordle') }}"
            class="block rounded-xl border border-gray-200 p-6 hover:shadow-lg transition">
            <h2 class="text-xl font-semibold">🔤 Wordle</h2>
            <p class="text-gray-500 mt-2">Guess the secret word in 6 tries. Logic and vocabulary.</p>
        </a>  
        <a href="{{ route('battleship') }}"
            class="block rounded-xl border border-gray-200 p-6 hover:shadow-lg transition">
            <h2 class="text-xl font-semibold">🚢 Battleship</h2>
            <p class="text-gray-500 mt-2">Classic naval combat. Sink the enemy fleet before they sink yours.</p>
        </a>
    </div>
</body>

</html>