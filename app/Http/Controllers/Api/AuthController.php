<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JettAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'Unable to register this user.',
            ], 422);
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
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid email or password.',
            ], 401);
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
}
