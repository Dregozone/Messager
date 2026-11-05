<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0b141a" media="(prefers-color-scheme: dark)">
        <meta name="theme-color" content="#f6f7f9" media="(prefers-color-scheme: light)">

        <title>{{ $title ?? 'Messager' }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full overflow-hidden bg-canvas text-ink antialiased">
        {{ $slot }}
    </body>
</html>
