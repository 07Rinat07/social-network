<!DOCTYPE html>
@php
    $rawPath = request()->getPathInfo() ?: '/';
    $basePath = preg_replace('#^/(ru|en)(?=/|$)#i', '', $rawPath) ?? '';
    $basePath = $basePath === '' ? '/' : $basePath;
    $pageLocale = request()->segment(1) === 'en' ? 'en' : 'ru';

    $seoByLocale = [
        'ru' => [
            'title' => 'Solid Social — социальная сеть с чатами, IPTV и радио',
            'description' => 'Современная социальная сеть: публикации, realtime-чаты, IPTV, радио и гибкие настройки контента.',
            'keywords' => 'социальная сеть, чаты онлайн, realtime чат, IPTV, интернет радио, лента публикаций, карусель медиа, личный кабинет, админ панель',
        ],
        'en' => [
            'title' => 'Solid Social — social network with chats, IPTV and radio',
            'description' => 'Modern social network with posts, realtime chats, IPTV, radio, and flexible content controls.',
            'keywords' => 'social network, realtime chat, IPTV player, internet radio, media carousel, user profile, admin panel',
        ],
    ];

    $seo = $seoByLocale[$pageLocale];
    $localePathSuffix = $basePath === '/' ? '' : $basePath;
    $canonicalUrl = url('/' . $pageLocale . $localePathSuffix);
    $ruAltUrl = url('/ru' . $localePathSuffix);
    $enAltUrl = url('/en' . $localePathSuffix);
@endphp
<html lang="{{ $pageLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="keywords" content="{{ $seo['keywords'] }}">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#0f6cf2">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="ru" href="{{ $ruAltUrl }}">
    <link rel="alternate" hreflang="en" href="{{ $enAltUrl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $ruAltUrl }}">

    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/logo-mark.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/favicon-32.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('brand/apple-touch-icon.png') }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Solid Social">
    <meta property="og:locale" content="{{ $pageLocale === 'en' ? 'en_US' : 'ru_RU' }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ asset('brand/og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/png">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
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
