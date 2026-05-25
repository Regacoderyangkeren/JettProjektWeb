<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\JettAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class FirebaseAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, JettAuthService $auth): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $auth->login($data['email'], $data['password']);
        } catch (Throwable) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Email atau password belum cocok.']);
        }

        $request->session()->regenerate();
        $request->session()->put('firebase', [
            'uid' => $result['uid'],
            'email' => $result['profile']['email'] ?? $data['email'],
            'profile' => $result['profile'],
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, JettAuthService $auth): RedirectResponse
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['nullable', 'string', 'max:80'],
            'alias' => ['nullable', 'alpha_dash', 'max:32'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $registered = $auth->register($data);
            $result = $auth->login($data['email'], $data['password']);
            $profile = array_merge($registered['profile'], $result['profile']);
        } catch (Throwable) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Akun belum bisa dibuat. Cek lagi datanya atau coba beberapa saat lagi.']);
        }

        $request->session()->regenerate();
        $request->session()->put('firebase', [
            'uid' => $result['uid'],
            'email' => $profile['email'] ?? $data['email'],
            'profile' => $profile,
        ]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request, JettAuthService $auth): RedirectResponse
    {
        $uid = $request->session()->get('firebase.uid');

        if (is_string($uid) && $uid !== '') {
            try {
                $auth->logout($uid);
            } catch (Throwable) {
                // Logout should still clear the Laravel session.
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
