<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>2048</title>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/game2048/main.jsx'])
</head>

<body>
    <div id="game2048-root"></div>
</body>

</html>