<div class="row-item">
    <span class="row-item-header">
        <h3>{{ $item['title'] ?? 'Inbox item' }}</h3>
        <span class="badge {{ ($item['read'] ?? false) ? '' : 'active' }}">{{ ($item['read'] ?? false) ? 'READ' : 'UNREAD' }}</span>
    </span>
    <span class="small muted">{{ $item['type'] ?? 'item' }}</span>
    <p>{{ $item['body'] ?? '' }}</p>
    <div class="toolbar" style="justify-content:flex-start;">
        @if (($item['type'] ?? '') === 'connection_chat' && ($item['actorId'] ?? '') !== '')
            <a class="action-link" href="{{ route('connections.chat', $item['actorId']) }}">Open chat</a>
        @endif
        @if (($item['type'] ?? '') === 'team_invite' && ! ($item['read'] ?? false) && ($item['inviteId'] ?? '') !== '')
            <form class="inline-form" method="post" action="{{ route('teams.invites.accept', $item['inviteId']) }}">
                @csrf
                <button type="submit">Accept</button>
            </form>
            <form class="inline-form" method="post" action="{{ route('teams.invites.decline', $item['inviteId']) }}">
                @csrf
                <button class="button-secondary" type="submit">Decline</button>
            </form>
        @endif
        <form class="inline-form" method="post" action="{{ route('inbox.read', $item['id'] ?? '') }}">
            @csrf
            @method('patch')
            <input type="hidden" name="box" value="{{ $box }}">
            <button class="button-secondary" type="submit">Mark read</button>
        </form>
        <form class="inline-form" method="post" action="{{ route('inbox.destroy', $item['id'] ?? '') }}">
            @csrf
            @method('delete')
            <input type="hidden" name="box" value="{{ $box }}">
            <button class="button-danger" type="submit">Remove</button>
        </form>
    </div>
</div>
