<?php

namespace App\Http\Middleware;

use App\Services\JettAuthService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureFirebaseBearer
{
    public function __construct(private readonly JettAuthService $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->unauthorized();
        }

        try {
            $result = $this->auth->verifyIdToken($token);
        } catch (Throwable) {
            return $this->unauthorized();
        }

        $request->attributes->set('firebase.uid', $result['uid']);
        $request->attributes->set('firebase.profile', $result['profile']);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Valid Firebase bearer token is required.',
        ], 401);
    }
}
