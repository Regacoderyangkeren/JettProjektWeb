@extends('layouts.app')

@php
    $connectionIds = $currentUser['connectionIds'] ?? [];
    $pendingIds = $currentUser['pendingConnectionIds'] ?? [];
    $sentIds = $currentUser['sentConnectionRequestIds'] ?? [];
    $connectedUsers = array_values(array_filter($users, fn ($user) => in_array($user['id'] ?? '', $connectionIds, true)));
    $pendingUsers = array_values(array_filter($users, fn ($user) => in_array($user['id'] ?? '', $pendingIds, true)));
    $discoverUsers = array_values(array_filter($users, fn ($user) => ! in_array($user['id'] ?? '', $connectionIds, true)));
@endphp

@section('body')
    <header class="page-header">
        <div class="page-title">
            <h1>Connections</h1>
            <p>{{ count($connectedUsers) }} connected, {{ count($pendingUsers) }} pending</p>
        </div>
    </header>

    <div class="content-grid">
        <section class="panel">
            <h2>People</h2>
            <div class="stack">
                @forelse ($discoverUsers as $user)
                    @php
                        $id = $user['id'] ?? '';
                        $isPending = in_array($id, $pendingIds, true);
                        $isSent = in_array($id, $sentIds, true);
                        $isConnected = in_array($id, $connectionIds, true);
                    @endphp
                    <div class="row-item">
                        <div class="person-row">
                            @include('connections.partials.avatar', ['user' => $user])
                            <div>
                                <h3>{{ trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')) ?: ($user['alias'] ?? $user['email'] ?? 'User') }}</h3>
                                <span class="small muted">{{ $user['email'] ?? '' }}</span>
                            </div>
                        </div>

                        <div class="toolbar" style="justify-content:flex-start;">
                            @if ($isPending)
                                <form class="inline-form" method="post" action="{{ route('connections.accept', $id) }}">
                                    @csrf
                                    <button type="submit">Accept</button>
                                </form>
                                <form class="inline-form" method="post" action="{{ route('connections.decline', $id) }}">
                                    @csrf
                                    <button class="button-secondary" type="submit">Decline</button>
                                </form>
                            @elseif ($isSent)
                                <span class="badge">REQUEST SENT</span>
                            @elseif ($isConnected)
                                <span class="badge active">CONNECTED</span>
                            @else
                                <form class="inline-form" method="post" action="{{ route('connections.request', $id) }}">
                                    @csrf
                                    <button type="submit">Connect</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">No users found.</div>
                @endforelse
            </div>
        </section>

        <div>
            <section class="panel">
                <h2>Pending requests</h2>
                <div class="stack">
                    @forelse ($pendingUsers as $user)
                        <div class="row-item">
                            <div class="person-row">
                                @include('connections.partials.avatar', ['user' => $user])
                                <div>
                                    <h3>{{ trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')) ?: ($user['alias'] ?? $user['email'] ?? 'User') }}</h3>
                                    <span class="small muted">{{ $user['email'] ?? '' }}</span>
                                </div>
                            </div>
                            <div class="toolbar" style="justify-content:flex-start;">
                                <form class="inline-form" method="post" action="{{ route('connections.accept', $user['id'] ?? '') }}">
                                    @csrf
                                    <button type="submit">Accept</button>
                                </form>
                                <form class="inline-form" method="post" action="{{ route('connections.decline', $user['id'] ?? '') }}">
                                    @csrf
                                    <button class="button-secondary" type="submit">Decline</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No pending requests.</div>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <h2>Your connections</h2>
                <div class="stack">
                    @forelse ($connectedUsers as $user)
                        <div class="row-item">
                            <div class="person-row">
                                @include('connections.partials.avatar', ['user' => $user])
                                <div>
                                    <h3>{{ trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')) ?: ($user['alias'] ?? $user['email'] ?? 'User') }}</h3>
                                    <span class="small muted">{{ $user['email'] ?? '' }}</span>
                                </div>
                            </div>
                            <div class="toolbar" style="justify-content:flex-start;">
                                <a class="action-link" href="{{ route('connections.chat', $user['id'] ?? '') }}">Chat</a>
                                <form class="inline-form" method="post" action="{{ route('connections.destroy', $user['id'] ?? '') }}">
                                    @csrf
                                    @method('delete')
                                    <button class="button-danger" type="submit">Remove</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">No connections yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
