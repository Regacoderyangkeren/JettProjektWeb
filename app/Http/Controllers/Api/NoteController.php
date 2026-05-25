<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class NoteController extends Controller
{
    public function index(Request $request, NoteService $notes): JsonResponse
    {
        $userId = $request->query('userId');

        return response()->json([
            'ok' => true,
            'notes' => $notes->forUser(is_string($userId) && $userId !== '' ? $userId : $this->currentUserId($request)),
        ]);
    }

    public function store(Request $request, NoteService $notes): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'note' => $notes->save($request->all(), $this->currentUserId($request)),
            ], 201);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function show(string $noteId, NoteService $notes): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'note' => $notes->findOrFail($noteId),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function update(Request $request, string $noteId, NoteService $notes): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'note' => $notes->save(array_merge($request->all(), ['id' => $noteId]), $this->currentUserId($request)),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function destroy(string $noteId, NoteService $notes): JsonResponse
    {
        try {
            $notes->delete($noteId);

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
            'message' => $exception->getMessage() ?: 'Note request failed.',
        ], $exception instanceof RuntimeException ? 404 : 422);
    }
}
