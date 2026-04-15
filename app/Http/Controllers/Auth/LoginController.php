<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    protected int $maxAttempts = 10;

    protected int $decaySeconds = 15;

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): Response|JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (RateLimiter::tooManyAttempts($this->throttleKey($request), $this->maxAttempts)) {
            return $this->sendLockoutResponse($request);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds);

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return response()->json(['redirect_to' => '/']);
    }

    public function logout(Request $request): Response|JsonResponse
    {
        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return response()->json(['redirect_to' => '/']);
    }

    protected function sendLockoutResponse(Request $request): JsonResponse
    {
        $seconds = max(1, (int) RateLimiter::availableIn($this->throttleKey($request)));
        $message = trans('auth.throttle', [
            'seconds' => $seconds,
            'minutes' => (int) ceil($seconds / 60),
        ]);

        return response()
            ->json([
                'message' => $message,
                'errors' => [
                    'email' => [$message],
                ],
                'retry_after' => $seconds,
            ], Response::HTTP_TOO_MANY_REQUESTS)
            ->header('Retry-After', (string) $seconds);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')) . '|' . $request->ip();
    }
}
