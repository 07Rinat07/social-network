<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfirmPasswordController extends Controller
{
    protected string $redirectTo = RouteServiceProvider::HOME;

    public function showConfirmForm(): View
    {
        return view('auth.passwords.confirm');
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), (string) $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => [trans('auth.password')],
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json(['redirect_to' => $this->redirectTo]);
    }
}
