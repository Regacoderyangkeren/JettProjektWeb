<div class="chat-stream">
    @forelse ($messages as $message)
        @php
            $isOwn = ($message['senderId'] ?? '') === $currentUserId;
            $timestamp = is_numeric($message['createdAt'] ?? null) ? (int) $message['createdAt'] : 0;
            $sentAt = $timestamp > 0
                ? \Carbon\Carbon::createFromTimestamp((int) floor($timestamp / 1000))->timezone(config('app.timezone'))->format('d M, H:i')
                : '';
        @endphp
        <article class="chat-message {{ $isOwn ? 'own' : '' }}">
            <div class="chat-message-head">
                <strong>{{ $isOwn ? 'You' : ($message['senderName'] ?? 'Member') }}</strong>
                <span>{{ $sentAt }}</span>
            </div>
            <p>{{ $message['body'] ?? '' }}</p>
        </article>
    @empty
        <div class="empty-state">No messages yet.</div>
    @endforelse
</div>
