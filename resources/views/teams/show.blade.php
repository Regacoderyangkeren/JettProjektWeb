@extends('layouts.app')

@php
    $isLeader = ($team['leaderId'] ?? '') === $currentUserId;
@endphp

@section('body')
    <header class="page-header">
        <div class="page-title">
            <h1>{{ $team['name'] ?? 'Team' }}</h1>
            <p>{{ $team['description'] ?? '' }}</p>
        </div>
        <div class="toolbar">
            <a class="action-link" href="{{ route('teams.chat', $team['id'] ?? '') }}">Team chat</a>
            @if ($isLeader)
                <form class="inline-form" method="post" action="{{ route('teams.destroy', $team['id'] ?? '') }}">
                    @csrf
                    @method('delete')
                    <button class="button-danger" type="submit">Delete team</button>
                </form>
            @else
                <form class="inline-form" method="post" action="{{ route('teams.leave', $team['id'] ?? '') }}">
                    @csrf
                    <button class="button-secondary" type="submit">Leave team</button>
                </form>
            @endif
        </div>
    </header>

    <div class="content-grid">
        <div>
            <section class="panel">
                <h2>Members</h2>
                <div class="stack">
                    @forelse ($members as $member)
                        @php
                            $user = $member['user'] ?? [];
                            $id = $user['id'] ?? '';
                            $isTeamLeader = $id === ($team['leaderId'] ?? '');
                        @endphp
                        <div class="row-item">
                            <div class="person-row">
                                @include('connections.partials.avatar', ['user' => $user])
                                <div>
                                    <h3>{{ trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')) ?: ($user['alias'] ?? $user['email'] ?? 'Member') }}</h3>
                                    <div class="tag-badges">
                                        @foreach (($member['tags'] ?? [$isTeamLeader ? 'leader' : 'member']) as $tagIndex => $tagName)
                                            @php
                                                $tagColor = $member['tagColors'][$tagIndex] ?? '#6C5CE7';
                                                $tagColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $tagColor) ? $tagColor : '#6C5CE7';
                                            @endphp
                                            <span class="tag-badge" style="border-color: {{ $tagColor }}; color: {{ $tagColor }};">{{ $tagName }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @if ($isLeader && ! $isTeamLeader)
                                <form class="inline-form" method="post" action="{{ route('teams.members.destroy', [$team['id'] ?? '', $id]) }}">
                                    @csrf
                                    @method('delete')
                                    <button class="button-danger" type="submit">Remove</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="empty-state">No members found.</div>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <h2>Projects</h2>
                <div class="stack">
                    @forelse ($linkedProjects as $project)
                        <a class="row-item" href="{{ route('projects.show', $project['id'] ?? '') }}">
                            <span class="row-item-header">
                                <h3>{{ $project['name'] ?? 'Untitled project' }}</h3>
                                <span class="badge {{ strtolower($project['status'] ?? 'active') }}">{{ $project['status'] ?? 'ACTIVE' }}</span>
                            </span>
                            <span class="small muted">{{ $project['description'] ?? '' }}</span>
                        </a>
                    @empty
                        <div class="empty-state">No linked projects.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div>
            @if ($isLeader)
                <section class="panel">
                    <h2>Invite member</h2>
                    @if (count($inviteCandidates) > 0)
                        <form method="post" action="{{ route('teams.invites.store', $team['id'] ?? '') }}">
                            @csrf
                            <label>
                                Connection
                                <select name="userId" required>
                                    <option value="">Select member</option>
                                    @foreach ($inviteCandidates as $user)
                                        <option value="{{ $user['id'] ?? '' }}">
                                            {{ trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')) ?: ($user['alias'] ?? $user['email'] ?? 'Member') }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit">Send invite</button>
                        </form>
                    @else
                        <div class="empty-state">No available connections to invite.</div>
                    @endif
                </section>

                <section class="panel">
                    <h2>Pending invitations</h2>
                    <div class="stack">
                        @forelse ($pendingInvites as $invite)
                            <div class="row-item">
                                <h3>{{ '@'.($invite['invitedAlias'] ?? 'member') }}</h3>
                                <span class="badge review">PENDING</span>
                            </div>
                        @empty
                            <div class="empty-state">No pending invitations.</div>
                        @endforelse
                    </div>
                </section>

                <section class="panel">
                    <h2>Member tags</h2>
                    <form method="post" action="{{ route('teams.tags.store', $team['id'] ?? '') }}">
                        @csrf
                        <div class="form-grid">
                            <label>
                                Tag name
                                <input type="text" name="name" maxlength="48" required>
                            </label>
                            <label>
                                Color
                                <input class="color-field" type="color" name="colorHex" value="#6C5CE7" required>
                            </label>
                            <fieldset class="full checkbox-fieldset">
                                <legend>Assign members</legend>
                                <div class="checkbox-grid">
                                    @foreach ($members as $member)
                                        @php $memberUser = $member['user'] ?? []; @endphp
                                        <label class="checkbox-row">
                                            <input type="checkbox" name="assignedMemberIds[]" value="{{ $memberUser['id'] ?? '' }}">
                                            <span>{{ trim(($memberUser['firstName'] ?? '').' '.($memberUser['lastName'] ?? '')) ?: ($memberUser['alias'] ?? 'Member') }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        </div>
                        <button type="submit">Create tag</button>
                    </form>

                    <div class="stack tag-editor-list">
                        @forelse (($tags ?? []) as $tag)
                            @php
                                $tagId = $tag['id'] ?? '';
                                $tagIsSystem = ($tag['system'] ?? false) || in_array($tagId, ['leader', 'member'], true);
                                $tagColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $tag['colorHex'] ?? '') ? $tag['colorHex'] : '#6C5CE7';
                            @endphp
                            @if ($tagIsSystem)
                                <div class="row-item tag-system-row">
                                    <span class="tag-badge" style="border-color: {{ $tagColor }}; color: {{ $tagColor }};">{{ $tag['name'] ?? $tagId }}</span>
                                    <span class="small muted">System tag</span>
                                </div>
                            @else
                                <form class="row-item tag-edit-form" method="post" action="{{ route('teams.tags.update', [$team['id'] ?? '', $tagId]) }}">
                                    @csrf
                                    @method('patch')
                                    <div class="form-grid">
                                        <label>
                                            Tag name
                                            <input type="text" name="name" value="{{ $tag['name'] ?? '' }}" maxlength="48" required>
                                        </label>
                                        <label>
                                            Color
                                            <input class="color-field" type="color" name="colorHex" value="{{ $tagColor }}" required>
                                        </label>
                                        <fieldset class="full checkbox-fieldset">
                                            <legend>Assigned members</legend>
                                            <div class="checkbox-grid">
                                                @foreach ($members as $member)
                                                    @php $memberUser = $member['user'] ?? []; @endphp
                                                    <label class="checkbox-row">
                                                        <input type="checkbox" name="assignedMemberIds[]" value="{{ $memberUser['id'] ?? '' }}" @checked(in_array($tagId, $member['tagIds'] ?? [], true))>
                                                        <span>{{ trim(($memberUser['firstName'] ?? '').' '.($memberUser['lastName'] ?? '')) ?: ($memberUser['alias'] ?? 'Member') }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    </div>
                                    <button class="button-secondary" type="submit">Update tag</button>
                                </form>
                            @endif
                        @empty
                            <div class="empty-state">Create a custom tag to organize member roles.</div>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection
