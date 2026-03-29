<?php

use Laravel\Sanctum\Sanctum;

$envStatefulDomains = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''))
)));

$appUrl = trim((string) env('APP_URL', ''));
$appHost = is_string(parse_url($appUrl, PHP_URL_HOST)) ? trim((string) parse_url($appUrl, PHP_URL_HOST)) : '';
$appPort = parse_url($appUrl, PHP_URL_PORT);
$appHostWithPort = $appHost !== ''
    ? $appHost.(is_int($appPort) ? ':'.$appPort : '')
    : '';

$statefulDomains = array_values(array_unique(array_filter(array_merge(
    [
        'localhost',
        'localhost:3000',
        '127.0.0.1',
        '127.0.0.1:8000',
        '::1',
    ],
    $envStatefulDomains,
    [
        trim((string) Sanctum::currentApplicationUrlWithPort(), " \t\n\r\0\x0B,"),
        $appHostWithPort,
    ]
), static fn ($value) => is_string($value) && $value !== '')));

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => $statefulDomains,

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. If this value is null, personal access tokens do
    | not expire. This won't tweak the lifetime of first-party sessions.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],

];
