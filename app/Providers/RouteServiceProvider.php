<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            if ($request->user()) {
                return Limit::perMinute(600)->by('api:user:' . $request->user()->id);
            }

            return Limit::perMinute(240)->by('api:guest:' . $request->ip());
        });

        RateLimiter::for('feedback', function (Request $request) {
            $perMinute = $this->app->environment('local') ? 120 : 20;
            $key = $request->user()
                ? 'feedback:user:' . $request->user()->getAuthIdentifier()
                : 'feedback:guest:' . $request->ip();

            return Limit::perMinute($perMinute)
                ->by($key)
                ->response(function (Request $request, array $headers): JsonResponse {
                    return $this->feedbackThrottleResponse($headers);
                });
        });
    }

    protected function feedbackThrottleResponse(array $headers): JsonResponse
    {
        $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));

        return response()->json([
            'message' => 'Слишком много попыток отправки. Попробуйте еще раз позже.',
            'retry_after_seconds' => $retryAfter,
        ], 429, $headers);
    }
}
