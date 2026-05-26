<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ChatController extends Controller
{
    public function connection(Request $request, string $userId, ChatService $chats): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'chat' => $chats->connectionThread($this->uid($request), $userId),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function sendConnection(Request $request, string $userId, ChatService $chats): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);

        try {
            return response()->json([
                'ok' => true,
                'message' => $chats->sendConnectionMessage($this->uid($request), $userId, $data['body']),
            ], 201);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function team(Request $request, string $teamId, ChatService $chats): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'chat' => $chats->teamThread($teamId, $this->uid($request)),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function sendTeam(Request $request, string $teamId, ChatService $chats): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);

        try {
            return response()->json([
                'ok' => true,
                'message' => $chats->sendTeamMessage($teamId, $this->uid($request), $data['body']),
            ], 201);
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
        $status = match (true) {
            $exception instanceof RuntimeException => 404,
            $exception instanceof DomainException => 403,
            default => 422,
        };

        return response()->json([
            'ok' => false,
            'message' => $exception->getMessage() ?: 'Chat request failed.',
        ], $status);
    }
}
