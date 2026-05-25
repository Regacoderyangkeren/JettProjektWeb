@extends('layouts.app')

@section('body')
    <header class="page-header">
        <div class="page-title">
            <h1>Teams</h1>
            <p>{{ count($teams) }} team{{ count($teams) === 1 ? '' : 's' }}</p>
        </div>
    </header>

    <div class="content-grid">
        <div>
            <section class="panel">
                <h2>Your teams</h2>
                <div class="stack">
                    @forelse ($teams as $team)
                        <a class="row-item" href="{{ route('teams.show', $team['id'] ?? '') }}">
                            <span class="row-item-header">
                                <h3>{{ $team['name'] ?? 'Untitled team' }}</h3>
                                <span class="badge">{{ count($team['memberIds'] ?? []) }} MEMBER{{ count($team['memberIds'] ?? []) === 1 ? '' : 'S' }}</span>
                            </span>
                            <span class="small muted">{{ $team['description'] ?? '' }}</span>
                            <span class="small muted">Led by {{ $team['leaderName'] ?? 'Member' }}</span>
                        </a>
                    @empty
                        <div class="empty-state">No teams yet.</div>
                    @endforelse
                </div>
            </section>

            @if (count($incomingInvites) > 0)
                <section class="panel">
                    <h2>Invitations</h2>
                    <div class="stack">
                        @foreach ($incomingInvites as $invite)
                            <div class="row-item">
                                <span class="row-item-header">
                                    <h3>{{ $invite['teamName'] ?? 'Team invitation' }}</h3>
                                    <span class="badge review">PENDING</span>
                                </span>
                                <p>{{ $invite['inviterName'] ?? 'A member' }} invited you to join.</p>
                                <div class="toolbar" style="justify-content:flex-start;">
                                    <form class="inline-form" method="post" action="{{ route('teams.invites.accept', $invite['id'] ?? '') }}">
                                        @csrf
                                        <button type="submit">Accept</button>
                                    </form>
                                    <form class="inline-form" method="post" action="{{ route('teams.invites.decline', $invite['id'] ?? '') }}">
                                        @csrf
                                        <button class="button-secondary" type="submit">Decline</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        <section class="panel">
            <h2>Create team</h2>
            <form method="post" action="{{ route('teams.store') }}">
                @csrf
                <label>
                    Name
                    <input name="name" value="{{ old('name') }}" required>
                </label>
                <label>
                    Description
                    <textarea name="description">{{ old('description') }}</textarea>
                </label>
                @if (count($connectedUsers) > 0)
                    <label>
                        Invite connections
                        <select name="teammateIds[]" multiple>
                            @foreach ($connectedUsers as $user)
                                <option value="{{ $user['id'] ?? '' }}" @selected(in_array($user['id'] ?? '', old('teammateIds', []), true))>
                                    {{ trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')) ?: ($user['alias'] ?? $user['email'] ?? 'Member') }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button type="submit">Create</button>
            </form>
        </section>
    </div>
@endsection
