@extends('layouts.app')

@section('body')
    <header class="page-header">
        <div class="person-row">
            @include('connections.partials.avatar', ['user' => $targetUser])
            <div class="page-title">
                <h1>{{ trim(($targetUser['firstName'] ?? '').' '.($targetUser['lastName'] ?? '')) ?: ($targetUser['alias'] ?? 'Connection') }}</h1>
                <p>Direct message</p>
            </div>
        </div>
        <a class="action-link" href="{{ route('connections.index') }}">Back to connections</a>
    </header>

    <section class="panel chat-panel">
        @include('chats.partials.stream', ['messages' => $messages, 'currentUserId' => $currentUserId])

        <form class="chat-compose" method="post" action="{{ route('connections.chat.messages.store', $targetUser['id'] ?? '') }}">
            @csrf
            <label>
                Message
                <textarea name="body" maxlength="3000" required>{{ old('body') }}</textarea>
            </label>
            <button type="submit">Send</button>
        </form>
    </section>
@endsection
