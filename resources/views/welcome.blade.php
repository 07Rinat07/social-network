<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solid Social</title>
    <meta name="description" content="Solid Social — современная SPA-соцсеть: чаты, медиа, радио, лента и сообщество.">
    <meta name="theme-color" content="#0f6cf2">

    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/logo-mark.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/favicon-32.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('brand/apple-touch-icon.png') }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Solid Social">
    <meta property="og:title" content="Solid Social">
    <meta property="og:description" content="Чаты, медиа, радио и лента в одной SPA-соцсети.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('brand/og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/png">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Solid Social">
    <meta name="twitter:description" content="Чаты, медиа, радио и лента в одной SPA-соцсети.">
    <meta name="twitter:image" content="{{ asset('brand/og-image.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Literata:opsz,wght@7..72,600;7..72,700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div id="app"></div>
</body>
</html>
