<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class InboxController extends Controller
{
    public function index(Request $request, InboxService $inbox): JsonResponse
    {
        $box = $request->query('box');
        $limit = $request->query('limit');

        return response()->json([
            'ok' => true,
            'items' => $inbox->list(
                $this->currentUserId($request),
                is_string($box) ? $box : 'inbox',
                is_numeric($limit) ? (int) $limit : 60
            ),
        ]);
    }

    public function store(Request $request, InboxService $inbox): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'item' => $inbox->create($request->all(), $this->currentUserId($request)),
            ], 201);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function markRead(Request $request, string $itemId, InboxService $inbox): JsonResponse
    {
        $box = $request->input('box', $request->query('box', 'inbox'));
        $notificationId = $request->input('notificationId', $request->query('notificationId'));

        try {
            $inbox->markRead(
                $this->currentUserId($request),
                $itemId,
                is_string($box) ? $box : 'inbox',
                is_string($notificationId) ? $notificationId : null
            );

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function destroy(Request $request, string $itemId, InboxService $inbox): JsonResponse
    {
        $box = $request->input('box', $request->query('box', 'inbox'));

        try {
            $inbox->delete($this->currentUserId($request), $itemId, is_string($box) ? $box : 'inbox');

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    private function currentUserId(Request $request): string
    {
        $uid = $request->attributes->get('firebase.uid');

        return is_string($uid) ? $uid : '';
    }

    private function error(Throwable $exception): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $exception->getMessage() ?: 'Inbox request failed.',
        ], $exception instanceof RuntimeException ? 404 : 422);
    }
}
