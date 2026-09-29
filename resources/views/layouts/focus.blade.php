{{--
    Odak düzeni: sitenin başlığı ve kenar çubuğu olmadan tek iş (Faz H5 — Defterim, mockup
    "Defterim 1": sol sütunda kendi logosu ve defter listesi, sağda editör). Section'lar: title, content.
--}}
<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Evrenkent') }} — @yield('title')</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fraunces:500,600,600i|figtree:400,500,600|eb-garamond:400,400i,500,600,600i&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-paper text-slate-800">
        @yield('content')
    </body>
</html>
