<?php

namespace App\Http\Controllers\Web;

use App\Domain\Auth\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        [$user, $token] = $this->authService->register($request->validated());

        Auth::login($user);
        session()->regenerate();
        session()->regenerateToken();
        session(['api_token' => $token]);

        return redirect()->route('dashboard')->with('status', 'Welcome to the world of monsters!');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        [$user, $token] = $this->authService->login($request->validated());

        Auth::login($user);
        session()->regenerate();
        session()->regenerateToken();
        session(['api_token' => $token]);

        return redirect()->intended(route('dashboard'))->with('status', 'Logged in successfully.');
    }

    public function logout(): RedirectResponse
    {
        session()->forget('api_token');
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect('/')->with('status', 'Logged out.');
    }
}
