@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <title>@yield('title', 'Calm')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * {
            corner-shape: squircle !important;
            -webkit-corner-shape: squircle !important;
        }
    </style>
</head>
<body class="min-h-screen antialiased">
    @yield('body')
</body>
</html>
