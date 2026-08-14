@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/favicon.png">
    <title>@yield('title', 'Calm')</title>
    @yield('meta')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * {
            corner-shape: squircle !important;
            -webkit-corner-shape: squircle !important;
        }
        /* True pills/circles — the page-wide squircle flattens huge radii in
           Chrome (corner-shape shipped), so these opt back into round. */
        .calm-round {
            corner-shape: round !important;
            -webkit-corner-shape: round !important;
        }
    </style>
</head>
<body class="min-h-screen antialiased">
    @yield('body')
    {{-- Global SPA-style login modal — any page can $dispatch('calm-open-login') --}}
    @if(! auth('api')->user())
        @include('partials._web_login_modal')
    @endif
</body>
</html>
