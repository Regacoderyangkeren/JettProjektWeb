<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JettAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth\SignIn\FailedToSignIn;
use Kreait\Firebase\Exception\Auth\EmailExists;
use Kreait\Firebase\Exception\Auth\OperationNotAllowed;
use Kreait\Firebase\Exception\Auth\WeakPassword;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request, JettAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['nullable', 'string', 'max:80'],
            'alias' => ['nullable', 'alpha_dash', 'max:32'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        try {
            $result = $auth->register($data);

            return response()->json([
                'ok' => true,
                'uid' => $result['uid'],
                'user' => $result['profile'],
            ], 201);
        } catch (EmailExists) {
            return response()->json([
                'ok' => false,
                'message' => 'This email is already registered.',
            ], 409);
        } catch (WeakPassword) {
            return response()->json([
                'ok' => false,
                'message' => 'The password does not meet Firebase requirements.',
            ], 422);
        } catch (OperationNotAllowed $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'Email and password registration is unavailable.',
            ], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'Registration service is temporarily unavailable.',
            ], 503);
        }
    }

    public function login(Request $request, JettAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $auth->login($data['email'], $data['password']);

            return response()->json([
                'ok' => true,
                'uid' => $result['uid'],
                'user' => $result['profile'],
                'token' => [
                    'type' => 'Bearer',
                    'idToken' => $result['idToken'],
                    'refreshToken' => $result['refreshToken'],
                    'expiresIn' => $result['expiresIn'],
                ],
            ]);
        } catch (FailedToSignIn $exception) {
            if ($this->isDisabledAccount($exception)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'This account is disabled.',
                ], 403);
            }

            if ($this->isInvalidCredentials($exception)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid email or password.',
                ], 401);
            }

            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'Authentication service is temporarily unavailable.',
            ], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'Authentication service is temporarily unavailable.',
            ], 503);
        }
    }

    public function me(Request $request, JettAuthService $auth): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'uid' => $request->attributes->get('firebase.uid'),
            'user' => $request->attributes->get('firebase.profile'),
        ]);
    }

    public function logout(Request $request, JettAuthService $auth): JsonResponse
    {
        try {
            $uid = $request->attributes->get('firebase.uid');
            if (is_string($uid) && $uid !== '') {
                $auth->logout($uid);
            }

            return response()->json([
                'ok' => true,
            ]);
        } catch (Throwable) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid or missing bearer token.',
            ], 401);
        }
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
