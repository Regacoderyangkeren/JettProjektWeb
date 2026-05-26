<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\JettAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Kreait\Firebase\Auth\SignIn\FailedToSignIn;
use Kreait\Firebase\Exception\Auth\EmailExists;
use Kreait\Firebase\Exception\Auth\OperationNotAllowed;
use Kreait\Firebase\Exception\Auth\WeakPassword;
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
        } catch (FailedToSignIn $exception) {
            if ($this->isDisabledAccount($exception)) {
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'Akun ini sedang dinonaktifkan.']);
            }

            if ($this->isInvalidCredentials($exception)) {
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'Email atau password belum cocok.']);
            }

            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Layanan login sedang bermasalah. Coba lagi sebentar.']);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Layanan login sedang bermasalah. Coba lagi sebentar.']);
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
        } catch (EmailExists) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Email ini sudah terdaftar. Silakan masuk.']);
        } catch (WeakPassword) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['password' => 'Password belum memenuhi ketentuan Firebase.']);
        } catch (OperationNotAllowed $exception) {
            report($exception);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Pendaftaran email dan password belum diaktifkan.']);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Layanan pendaftaran sedang bermasalah. Coba lagi sebentar.']);
        }

        try {
            $result = $auth->login($data['email'], $data['password']);
            $profile = array_merge($registered['profile'], $result['profile']);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')
                ->withErrors(['email' => 'Akun sudah dibuat, tetapi sesi belum dapat dibuka. Silakan masuk lagi.']);
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

    private function isInvalidCredentials(FailedToSignIn $exception): bool
    {
        $message = strtoupper($exception->getMessage());

        return str_contains($message, 'INVALID_LOGIN_CREDENTIALS')
            || str_contains($message, 'INVALID_PASSWORD')
            || str_contains($message, 'EMAIL_NOT_FOUND');
    }

    private function isDisabledAccount(FailedToSignIn $exception): bool
    {
        return str_contains(strtoupper($exception->getMessage()), 'USER_DISABLED');
    }
}
