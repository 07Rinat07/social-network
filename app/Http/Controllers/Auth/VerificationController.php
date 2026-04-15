<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    protected string $redirectTo = RouteServiceProvider::HOME;

    public function notice(): View
    {
        return view('auth.verify');
    }

    public function verify(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        abort_unless((string) $user->getKey() === (string) $request->route('id'), 403);
        abort_unless(hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Email подтвержден.']);
        }

        return redirect($this->redirectTo);
    }

    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email уже подтвержден.',
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Письмо для подтверждения email отправлено.',
        ]);
    }
}
