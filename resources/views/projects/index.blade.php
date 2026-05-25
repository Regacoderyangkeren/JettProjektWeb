@extends('layouts.app')

@section('body')
    <header class="page-header">
        <div class="page-title">
            <h1>Projects</h1>
            <p>{{ count($projects) }} project{{ count($projects) === 1 ? '' : 's' }}</p>
        </div>
    </header>

    <div class="content-grid">
        <section class="panel">
            <h2>Project list</h2>
            <div class="stack">
                @forelse ($projects as $project)
                    <a class="row-item" href="{{ route('projects.show', $project['id'] ?? '') }}">
                        <span class="row-item-header">
                            <h3>{{ $project['name'] ?? 'Untitled project' }}</h3>
                            <span class="badge {{ strtolower($project['status'] ?? 'active') }}">{{ $project['status'] ?? 'ACTIVE' }}</span>
                        </span>
                        <span class="small muted">{{ $project['description'] ?? '' }}</span>
                        <span class="small muted">{{ count($project['memberIds'] ?? []) }} member{{ count($project['memberIds'] ?? []) === 1 ? '' : 's' }}</span>
                    </a>
                @empty
                    <div class="empty-state">No projects yet.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <h2>Create project</h2>
            <form method="post" action="{{ route('projects.store') }}">
                @csrf
                <label>
                    Name
                    <input name="name" value="{{ old('name') }}" required>
                </label>
                <label>
                    Description
                    <textarea name="description">{{ old('description') }}</textarea>
                </label>
                <div class="form-grid">
                    <label>
                        Color
                        <input name="colorHex" value="{{ old('colorHex', '#2F80ED') }}">
                    </label>
                    <label>
                        Team name
                        <input name="teamName" value="{{ old('teamName') }}">
                    </label>
                    <label>
                        Start
                        <input type="date" name="startAt" value="{{ old('startAt') }}">
                    </label>
                    <label>
                        End
                        <input type="date" name="endAt" value="{{ old('endAt') }}">
                    </label>
                </div>
                <button type="submit">Create</button>
            </form>
        </section>
    </div>
@endsection
