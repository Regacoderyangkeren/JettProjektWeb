@extends('layouts.app')

@php
    $projectStatus = $project['status'] ?? 'ACTIVE';
    $viewOnly = $projectStatus !== 'ACTIVE';
@endphp

@section('body')
    <header class="page-header">
        <div class="page-title">
            <h1>{{ $project['name'] ?? 'Untitled project' }}</h1>
            <p>{{ $project['description'] ?? '' }}</p>
        </div>

        <div class="toolbar">
            <span class="badge {{ strtolower($projectStatus) }}">{{ $projectStatus }}</span>
            <a class="button-ghost" style="display:inline-flex;align-items:center;min-height:44px;padding:10px 14px;border-radius:6px;" href="{{ route('projects.index') }}">Back</a>
        </div>
    </header>

    <div class="content-grid">
        <section class="panel">
            <h2>Tasks</h2>
            <div class="stack">
                @forelse ($tasks as $task)
                    @php
                        $taskStatus = $task['status'] ?? 'TODO';
                        $isReview = ($task['type'] ?? '') === 'REVIEW';
                    @endphp
                    <div class="row-item">
                        <span class="row-item-header">
                            <h3>{{ $task['title'] ?? 'Untitled task' }}</h3>
                            <span class="badge {{ $isReview ? 'review' : strtolower($taskStatus) }}">{{ $isReview ? 'REVIEW' : $taskStatus }}</span>
                        </span>
                        <span class="small muted">{{ $task['description'] ?? '' }}</span>
                        <span class="small muted">Priority: {{ $task['priority'] ?? 'MEDIUM' }} @if (($task['reviewState'] ?? '') !== '') | Review: {{ $task['reviewState'] }} @endif</span>

                        @if (! $viewOnly)
                            <div class="toolbar" style="justify-content:flex-start;">
                                @if (! $isReview)
                                    <form class="inline-form" method="post" action="{{ route('tasks.status', $task['id'] ?? '') }}">
                                        @csrf
                                        @method('patch')
                                        <select name="status">
                                            @foreach (['TODO', 'IN_PROGRESS', 'DONE', 'CANCELLED'] as $status)
                                                <option value="{{ $status }}" @selected($taskStatus === $status)>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                        <button class="button-secondary" type="submit">Update</button>
                                    </form>
                                @else
                                    <form class="inline-form" method="post" action="{{ route('tasks.review', $task['id'] ?? '') }}">
                                        @csrf
                                        <input type="hidden" name="approved" value="1">
                                        <button type="submit">Approve</button>
                                    </form>
                                    <form class="inline-form" method="post" action="{{ route('tasks.review', $task['id'] ?? '') }}">
                                        @csrf
                                        <input type="hidden" name="approved" value="0">
                                        <input name="reason" placeholder="Reason">
                                        <button class="button-secondary" type="submit">Return</button>
                                    </form>
                                @endif
                                <form class="inline-form" method="post" action="{{ route('tasks.destroy', $task['id'] ?? '') }}">
                                    @csrf
                                    @method('delete')
                                    <button class="button-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">No tasks yet.</div>
                @endforelse
            </div>
        </section>

        <div>
            <section class="panel">
                <h2>Project settings</h2>
                <form method="post" action="{{ route('projects.update', $project['id'] ?? '') }}">
                    @csrf
                    @method('patch')
                    <label>
                        Name
                        <input name="name" value="{{ old('name', $project['name'] ?? '') }}" @disabled($viewOnly) required>
                    </label>
                    <label>
                        Description
                        <textarea name="description" @disabled($viewOnly)>{{ old('description', $project['description'] ?? '') }}</textarea>
                    </label>
                    <div class="form-grid">
                        <label>
                            Color
                            <input name="colorHex" value="{{ old('colorHex', $project['colorHex'] ?? '#2F80ED') }}" @disabled($viewOnly)>
                        </label>
                        <label>
                            Team
                            <input name="teamName" value="{{ old('teamName', $project['teamName'] ?? '') }}" @disabled($viewOnly)>
                        </label>
                    </div>
                    <button type="submit" @disabled($viewOnly)>Save</button>
                </form>

                <div class="toolbar" style="justify-content:flex-start;margin-top:16px;">
                    <form class="inline-form" method="post" action="{{ route('projects.complete', $project['id'] ?? '') }}">
                        @csrf
                        <button class="button-secondary" type="submit" @disabled($viewOnly)>Complete</button>
                    </form>
                    <form class="inline-form" method="post" action="{{ route('projects.archive', $project['id'] ?? '') }}">
                        @csrf
                        <button class="button-secondary" type="submit" @disabled($viewOnly)>Archive</button>
                    </form>
                    <form class="inline-form" method="post" action="{{ route('projects.destroy', $project['id'] ?? '') }}">
                        @csrf
                        @method('delete')
                        <button class="button-danger" type="submit" @disabled($viewOnly)>Delete</button>
                    </form>
                </div>
            </section>

            <section class="panel">
                <h2>Create task</h2>
                <form method="post" action="{{ route('tasks.store', $project['id'] ?? '') }}">
                    @csrf
                    <label>
                        Title
                        <input name="title" value="{{ old('title') }}" @disabled($viewOnly) required>
                    </label>
                    <label>
                        Description
                        <textarea name="description" @disabled($viewOnly)>{{ old('description') }}</textarea>
                    </label>
                    <div class="form-grid">
                        <label>
                            Type
                            <select name="type" @disabled($viewOnly)>
                                @foreach (['TODO_LIST', 'RESEARCH', 'ISSUE', 'IMPROVEMENT', 'REQUEST'] as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Priority
                            <select name="priority" @disabled($viewOnly)>
                                @foreach (['LOW', 'MEDIUM', 'HIGH'] as $priority)
                                    <option value="{{ $priority }}" @selected($priority === 'MEDIUM')>{{ $priority }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Assignee ID
                            <input name="assignedTo" @disabled($viewOnly)>
                        </label>
                        <label>
                            Reviewer ID
                            <input name="reviewerId" @disabled($viewOnly)>
                        </label>
                        <label>
                            Due date
                            <input type="date" name="dueDate" @disabled($viewOnly)>
                        </label>
                    </div>
                    <button type="submit" @disabled($viewOnly)>Create task</button>
                </form>
            </section>
        </div>
    </div>
@endsection
