<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProjectService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectService $projects): JsonResponse
    {
        $ownerId = $request->query('ownerId');
        $memberId = $request->query('memberId');
        $currentUserId = $this->currentUserId($request);

        $items = is_string($ownerId) && $ownerId !== ''
            ? $projects->forOwner($ownerId)
            : $projects->forMember(is_string($memberId) && $memberId !== '' ? $memberId : $currentUserId);

        return response()->json([
            'ok' => true,
            'projects' => $items,
        ]);
    }

    public function store(Request $request, ProjectService $projects): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'colorHex' => ['nullable', 'string', 'max:20'],
            'ownerId' => ['nullable', 'string', 'max:120'],
            'teamId' => ['nullable', 'string', 'max:120'],
            'teamName' => ['nullable', 'string', 'max:160'],
            'memberIds' => ['nullable', 'array'],
            'memberIds.*' => ['string', 'max:120'],
            'startAt' => ['nullable', 'integer'],
            'endAt' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'ok' => true,
            'project' => $projects->create($data, $this->currentUserId($request)),
        ], 201);
    }

    public function show(string $projectId, ProjectService $projects): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'project' => $projects->findOrFail($projectId),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function update(Request $request, string $projectId, ProjectService $projects): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'project' => $projects->update($projectId, $request->all()),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function addMember(Request $request, string $projectId, ProjectService $projects): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'string', 'max:120'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'project' => $projects->addMember($projectId, $data['userId']),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function removeMember(string $projectId, string $userId, ProjectService $projects): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'project' => $projects->removeMember($projectId, $userId),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function complete(string $projectId, ProjectService $projects): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'project' => $projects->complete($projectId),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function archive(string $projectId, ProjectService $projects): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'project' => $projects->archive($projectId),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function destroy(string $projectId, ProjectService $projects): JsonResponse
    {
        try {
            $projects->delete($projectId);

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
        $status = match (true) {
            $exception instanceof RuntimeException => 404,
            $exception instanceof DomainException => 409,
            default => 422,
        };

        return response()->json([
            'ok' => false,
            'message' => $exception->getMessage() ?: 'Project request failed.',
        ], $status);
    }
}
