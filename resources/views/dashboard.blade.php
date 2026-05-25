@extends('layouts.app')

@section('body')
    <header class="page-header">
        <div class="page-title">
            <h1>Dashboard</h1>
            <p>{{ trim(($profile['firstName'] ?? '').' '.($profile['lastName'] ?? '')) ?: ($profile['email'] ?? 'Signed in') }}</p>
        </div>

        <form class="logout-form" method="post" action="{{ route('logout') }}">
            @csrf
            <button class="button-secondary" type="submit">Logout</button>
        </form>
    </header>

    <section class="summary-grid">
        <div class="metric">
            <strong>{{ count($projects) }}</strong>
            <span>Projects</span>
        </div>
        <div class="metric">
            <strong>{{ count($tasks) }}</strong>
            <span>Workload</span>
        </div>
        <div class="metric">
            <strong>{{ collect($inboxItems)->where('read', false)->count() }}</strong>
            <span>Unread inbox</span>
        </div>
        <div class="metric">
            <strong>{{ count($notes) }}</strong>
            <span>Notes</span>
        </div>
    </section>

    <div class="content-grid" style="margin-top: 18px;">
        <section class="panel">
            <h2>Recent projects</h2>
            <div class="stack">
                @forelse (array_slice($projects, 0, 5) as $project)
                    <a class="row-item" href="{{ route('projects.show', $project['id'] ?? '') }}">
                        <span class="row-item-header">
                            <h3>{{ $project['name'] ?? 'Untitled project' }}</h3>
                            <span class="badge {{ strtolower($project['status'] ?? 'active') }}">{{ $project['status'] ?? 'ACTIVE' }}</span>
                        </span>
                        <span class="small muted">{{ $project['description'] ?? '' }}</span>
                    </a>
                @empty
                    <div class="empty-state">No projects yet.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <h2>Inbox</h2>
            <div class="stack">
                @forelse ($inboxItems as $item)
                    <div class="row-item">
                        <span class="row-item-header">
                            <h3>{{ $item['title'] ?? 'Inbox item' }}</h3>
                            <span class="badge">{{ $item['type'] ?? 'item' }}</span>
                        </span>
                        <span class="small muted">{{ $item['body'] ?? '' }}</span>
                    </div>
                @empty
                    <div class="empty-state">Inbox is empty.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
