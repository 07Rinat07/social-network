<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;
    protected $maxAttempts = 10;
    protected $decayMinutes = 0.25;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    protected function sendLockoutResponse(Request $request)
    {
        $seconds = max(1, (int) $this->limiter()->availableIn($this->throttleKey($request)));
        $message = trans('auth.throttle', [
            'seconds' => $seconds,
            'minutes' => (int) ceil($seconds / 60),
        ]);

        if ($request->expectsJson()) {
            return response()
                ->json([
                    'message' => $message,
                    'errors' => [
                        $this->username() => [$message],
                    ],
                    'retry_after' => $seconds,
                ], Response::HTTP_TOO_MANY_REQUESTS)
                ->header('Retry-After', (string) $seconds);
        }

        throw ValidationException::withMessages([
            $this->username() => [$message],
        ])->status(Response::HTTP_TOO_MANY_REQUESTS);
    }
}
