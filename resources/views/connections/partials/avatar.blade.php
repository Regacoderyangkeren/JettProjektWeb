@php
    $name = trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')) ?: ($user['alias'] ?? $user['email'] ?? 'U');
    $initial = strtoupper(substr($name, 0, 1));
    $status = strtolower($user['status'] ?? 'offline');
@endphp

<div class="avatar" title="{{ $name }}">
    @if (($user['profilePictureUrl'] ?? '') !== '')
        <img src="{{ $user['profilePictureUrl'] }}" alt="">
    @else
        {{ $initial }}
    @endif
    <span class="status-dot {{ $status }}"></span>
</div>
