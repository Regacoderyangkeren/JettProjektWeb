<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class TaskPageController extends Controller
{
    public function store(Request $request, string $projectId, TaskService $tasks): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:220'],
            'description' => ['nullable', 'string'],
            'assignedTo' => ['nullable', 'string', 'max:120'],
            'reviewerId' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', 'string', 'max:40'],
            'dueDate' => ['nullable', 'date'],
        ]);

        $payload = array_merge($data, [
            'projectId' => $projectId,
            'createdBy' => (string) $request->session()->get('firebase.uid', ''),
            'dueDate' => $this->dateMillis($data['dueDate'] ?? null),
        ]);

        try {
            $tasks->create($payload, (string) $request->session()->get('firebase.uid', ''));

            return back()->with('status', 'Task created.');
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['task' => $exception->getMessage()]);
        }
    }

    public function status(Request $request, string $taskId, TaskService $tasks): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:40'],
        ]);

        try {
            $tasks->updateStatus($taskId, strtoupper($data['status']));

            return back()->with('status', 'Task status updated.');
        } catch (Throwable $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }
    }

    public function review(Request $request, string $taskId, TaskService $tasks): RedirectResponse
    {
        $data = $request->validate([
            'approved' => ['required', 'boolean'],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $tasks->completeReview($taskId, (bool) $data['approved'], (string) ($data['reason'] ?? ''));

            return back()->with('status', 'Review resolved.');
        } catch (Throwable $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }
    }

    public function destroy(string $taskId, TaskService $tasks): RedirectResponse
    {
        try {
            $tasks->delete($taskId);

            return back()->with('status', 'Task deleted.');
        } catch (Throwable $exception) {
            return back()->withErrors(['task' => $exception->getMessage()]);
        }
    }

    private function dateMillis(?string $date): int
    {
        if (! is_string($date) || $date === '') {
            return 0;
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? 0 : $timestamp * 1000;
    }
}
