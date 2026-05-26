@extends('layouts.app')

@section('body')
    <header class="page-header">
        <div class="page-title">
            <h1>{{ $team['name'] ?? 'Team' }} chat</h1>
            <p>Messages shared with team members</p>
        </div>
        <a class="action-link" href="{{ route('teams.show', $team['id'] ?? '') }}">Back to team</a>
    </header>

    <section class="panel chat-panel">
        @include('chats.partials.stream', ['messages' => $messages, 'currentUserId' => $currentUserId])

        <form class="chat-compose" method="post" action="{{ route('teams.chat.messages.store', $team['id'] ?? '') }}">
            @csrf
            <label>
                Message
                <textarea name="body" maxlength="3000" required>{{ old('body') }}</textarea>
            </label>
            <button type="submit">Send</button>
        </form>
    </section>
@endsection
