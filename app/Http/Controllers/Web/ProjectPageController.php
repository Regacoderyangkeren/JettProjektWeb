<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ProjectService;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ProjectPageController extends Controller
{
    public function index(Request $request, ProjectService $projects): View
    {
        return view('projects.index', [
            'projects' => $this->attempt(fn () => $projects->forMember($this->uid($request)), []),
        ]);
    }

    public function store(Request $request, ProjectService $projects): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'colorHex' => ['nullable', 'string', 'max:20'],
            'teamId' => ['nullable', 'string', 'max:120'],
            'teamName' => ['nullable', 'string', 'max:160'],
            'startAt' => ['nullable', 'date'],
            'endAt' => ['nullable', 'date'],
        ]);

        $payload = array_merge($data, [
            'ownerId' => $this->uid($request),
            'memberIds' => [$this->uid($request)],
            'startAt' => $this->dateMillis($data['startAt'] ?? null),
            'endAt' => $this->dateMillis($data['endAt'] ?? null),
        ]);

        try {
            $project = $projects->create($payload, $this->uid($request));

            return redirect()->route('projects.show', $project['id'])->with('status', 'Project created.');
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['project' => $exception->getMessage()]);
        }
    }

    public function show(string $projectId, ProjectService $projects, TaskService $tasks): View|RedirectResponse
    {
        try {
            return view('projects.show', [
                'project' => $projects->findOrFail($projectId),
                'tasks' => $tasks->forProject($projectId),
            ]);
        } catch (Throwable $exception) {
            return redirect()->route('projects.index')->withErrors(['project' => $exception->getMessage()]);
        }
    }

    public function update(Request $request, string $projectId, ProjectService $projects): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'colorHex' => ['nullable', 'string', 'max:20'],
            'teamName' => ['nullable', 'string', 'max:160'],
            'startAt' => ['nullable', 'date'],
            'endAt' => ['nullable', 'date'],
        ]);

        $payload = array_merge($data, [
            'startAt' => $this->dateMillis($data['startAt'] ?? null),
            'endAt' => $this->dateMillis($data['endAt'] ?? null),
        ]);

        try {
            $projects->update($projectId, $payload);

            return back()->with('status', 'Project updated.');
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['project' => $exception->getMessage()]);
        }
    }

    public function complete(string $projectId, ProjectService $projects): RedirectResponse
    {
        try {
            $projects->complete($projectId);

            return back()->with('status', 'Project completed.');
        } catch (Throwable $exception) {
            return back()->withErrors(['project' => $exception->getMessage()]);
        }
    }

    public function archive(string $projectId, ProjectService $projects): RedirectResponse
    {
        try {
            $projects->archive($projectId);

            return back()->with('status', 'Project archived.');
        } catch (Throwable $exception) {
            return back()->withErrors(['project' => $exception->getMessage()]);
        }
    }

    public function destroy(string $projectId, ProjectService $projects): RedirectResponse
    {
        try {
            $projects->delete($projectId);

            return redirect()->route('projects.index')->with('status', 'Project deleted.');
        } catch (Throwable $exception) {
            return back()->withErrors(['project' => $exception->getMessage()]);
        }
    }

    private function uid(Request $request): string
    {
        return (string) $request->session()->get('firebase.uid', '');
    }

    private function dateMillis(?string $date): int
    {
        if (! is_string($date) || $date === '') {
            return 0;
        }

        $timestamp = strtotime($date);

        return $timestamp === false ? 0 : $timestamp * 1000;
    }

    private function attempt(callable $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
