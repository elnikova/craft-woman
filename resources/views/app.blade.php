<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Craft Woman') }}</title>
    <link rel="icon" href="/images/ornaments/star-logo.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Commissioner:FLAR,VOLM,wght@0..100,0..100,100..900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="font-sans antialiased">
    <div id="app"></div>
</body>
</html>
