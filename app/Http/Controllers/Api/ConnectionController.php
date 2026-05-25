<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ConnectionController extends Controller
{
    public function index(Request $request, ConnectionService $connections): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'connections' => $connections->overview($this->uid($request)),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function request(Request $request, string $userId, ConnectionService $connections): JsonResponse
    {
        try {
            $connections->request($this->uid($request), $userId);

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function accept(Request $request, string $userId, ConnectionService $connections): JsonResponse
    {
        try {
            $connections->accept($this->uid($request), $userId);

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function decline(Request $request, string $userId, ConnectionService $connections): JsonResponse
    {
        try {
            $connections->decline($this->uid($request), $userId);

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function destroy(Request $request, string $userId, ConnectionService $connections): JsonResponse
    {
        try {
            $connections->remove($this->uid($request), $userId);

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    private function uid(Request $request): string
    {
        $uid = $request->attributes->get('firebase.uid');

        return is_string($uid) ? $uid : '';
    }

    private function error(Throwable $exception): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $exception->getMessage() ?: 'Connection request failed.',
        ], $exception instanceof RuntimeException ? 404 : 422);
    }
}
