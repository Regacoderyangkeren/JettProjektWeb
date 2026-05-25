<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaskService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class TaskController extends Controller
{
    public function index(Request $request, TaskService $tasks): JsonResponse
    {
        $projectId = $request->query('projectId');
        $assigneeId = $request->query('assigneeId');
        $workloadUserId = $request->query('workloadUserId');

        $items = match (true) {
            is_string($projectId) && $projectId !== '' => $tasks->forProject($projectId),
            is_string($assigneeId) && $assigneeId !== '' => $tasks->forAssignee($assigneeId),
            is_string($workloadUserId) && $workloadUserId !== '' => $tasks->workload($workloadUserId),
            default => $tasks->workload($this->currentUserId($request)),
        };

        return response()->json([
            'ok' => true,
            'tasks' => $items,
        ]);
    }

    public function store(Request $request, TaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:220'],
            'description' => ['nullable', 'string'],
            'assignedTo' => ['nullable', 'string', 'max:120'],
            'reviewerId' => ['nullable', 'string', 'max:120'],
            'createdBy' => ['nullable', 'string', 'max:120'],
            'projectId' => ['required', 'string', 'max:120'],
            'parentTaskId' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', 'string', 'max:40'],
            'dueDate' => ['nullable', 'integer'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'task' => $tasks->create(array_merge($request->all(), $data), $this->currentUserId($request)),
            ], 201);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function show(string $taskId, TaskService $tasks): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'task' => $tasks->findOrFail($taskId),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function update(Request $request, string $taskId, TaskService $tasks): JsonResponse
    {
        try {
            return response()->json([
                'ok' => true,
                'task' => $tasks->update($taskId, $request->all()),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function updateStatus(Request $request, string $taskId, TaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:40'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'task' => $tasks->updateStatus($taskId, strtoupper($data['status'])),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function completeReview(Request $request, string $taskId, TaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'approved' => ['required', 'boolean'],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'task' => $tasks->completeReview($taskId, (bool) $data['approved'], (string) ($data['reason'] ?? '')),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function setPinned(Request $request, string $taskId, TaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'pinned' => ['required', 'boolean'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'task' => $tasks->setPinned($taskId, (bool) $data['pinned']),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function setPriorityMarked(Request $request, string $taskId, TaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'marked' => ['required', 'boolean'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'task' => $tasks->setPriorityMarked($taskId, (bool) $data['marked']),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function addAttachment(Request $request, string $taskId, TaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'attachment' => ['required', 'array'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'task' => $tasks->addAttachment($taskId, $data['attachment']),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function removeAttachment(Request $request, string $taskId, TaskService $tasks): JsonResponse
    {
        $data = $request->validate([
            'attachment' => ['required', 'array'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'task' => $tasks->removeAttachment($taskId, $data['attachment']),
            ]);
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function destroy(string $taskId, TaskService $tasks): JsonResponse
    {
        try {
            $tasks->delete($taskId);

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
            'message' => $exception->getMessage() ?: 'Task request failed.',
        ], $status);
    }
}
