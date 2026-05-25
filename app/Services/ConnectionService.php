<?php

namespace App\Services;

use App\Services\Firebase\FirebaseService;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\FieldValue;
use RuntimeException;

class ConnectionService
{
    private const STANDBY_AFTER_MS = 6 * 60 * 60 * 1000;

    public function __construct(private readonly FirebaseService $firebase)
    {
    }

    public function overview(string $currentUserId): array
    {
        $currentUser = $this->findOrFail($currentUserId);
        $users = $this->allUsers();

        return [
            'currentUser' => $currentUser,
            'users' => array_values(array_filter(
                $users,
                fn (array $user): bool => ($user['id'] ?? '') !== $currentUserId
            )),
        ];
    }

    public function allUsers(): array
    {
        $users = [];

        foreach ($this->collection()->documents() as $document) {
            if ($document->exists()) {
                $users[] = $this->resolveStatus($document->data());
            }
        }

        usort($users, fn (array $left, array $right): int => strcasecmp($this->displayName($left), $this->displayName($right)));

        return $users;
    }

    public function findOrFail(string $userId): array
    {
        if ($userId === '') {
            throw new RuntimeException('User not found.');
        }

        $snapshot = $this->document($userId)->snapshot();

        if (! $snapshot->exists()) {
            throw new RuntimeException('User not found.');
        }

        return $this->resolveStatus($snapshot->data());
    }

    public function request(string $currentUserId, string $targetUserId): void
    {
        if ($currentUserId === '' || $targetUserId === '' || $currentUserId === $targetUserId) {
            return;
        }

        $currentUser = $this->findOrFail($currentUserId);
        $targetUser = $this->findOrFail($targetUserId);

        if (in_array($targetUserId, $currentUser['connectionIds'] ?? [], true)) {
            return;
        }

        if (in_array($targetUserId, $currentUser['sentConnectionRequestIds'] ?? [], true)) {
            return;
        }

        if (in_array($currentUserId, $targetUser['pendingConnectionIds'] ?? [], true)) {
            return;
        }

        $requestId = $this->connectionRequestId($currentUserId, $targetUserId);
        $now = $this->firebase->nowMillis();
        $inboxItem = [
            'id' => $requestId,
            'userId' => $targetUserId,
            'type' => 'connection_request',
            'title' => $this->displayName($currentUser, 'Someone').' wants to connect',
            'body' => 'Add @'.$this->alias($currentUser).' to your connections.',
            'actorId' => $currentUserId,
            'read' => false,
            'createdAt' => $now,
        ];

        $this->document($currentUserId)->update([
            ['path' => 'sentConnectionRequestIds', 'value' => FieldValue::arrayUnion([$targetUserId])],
        ]);
        $this->document($targetUserId)->update([
            ['path' => 'pendingConnectionIds', 'value' => FieldValue::arrayUnion([$currentUserId])],
        ]);
        $this->userInboxDocument($targetUserId, $requestId)->set($inboxItem, ['merge' => true]);
        $this->notificationDocument($requestId)->set($inboxItem, ['merge' => true]);
    }

    public function accept(string $currentUserId, string $requesterId): void
    {
        if ($currentUserId === '' || $requesterId === '' || $currentUserId === $requesterId) {
            return;
        }

        $this->findOrFail($currentUserId);
        $this->findOrFail($requesterId);

        $requestId = $this->connectionRequestId($requesterId, $currentUserId);
        $this->document($currentUserId)->update([
            ['path' => 'connectionIds', 'value' => FieldValue::arrayUnion([$requesterId])],
            ['path' => 'pendingConnectionIds', 'value' => FieldValue::arrayRemove([$requesterId])],
        ]);
        $this->document($requesterId)->update([
            ['path' => 'connectionIds', 'value' => FieldValue::arrayUnion([$currentUserId])],
            ['path' => 'sentConnectionRequestIds', 'value' => FieldValue::arrayRemove([$currentUserId])],
        ]);
        $this->markRequestRead($currentUserId, $requestId);
    }

    public function decline(string $currentUserId, string $requesterId): void
    {
        if ($currentUserId === '' || $requesterId === '' || $currentUserId === $requesterId) {
            return;
        }

        $requestId = $this->connectionRequestId($requesterId, $currentUserId);
        $this->document($currentUserId)->update([
            ['path' => 'pendingConnectionIds', 'value' => FieldValue::arrayRemove([$requesterId])],
        ]);
        $this->document($requesterId)->update([
            ['path' => 'sentConnectionRequestIds', 'value' => FieldValue::arrayRemove([$currentUserId])],
        ]);
        $this->markRequestRead($currentUserId, $requestId);
    }

    public function remove(string $currentUserId, string $targetUserId): void
    {
        if ($currentUserId === '' || $targetUserId === '' || $currentUserId === $targetUserId) {
            return;
        }

        $this->document($currentUserId)->update([
            ['path' => 'connectionIds', 'value' => FieldValue::arrayRemove([$targetUserId])],
        ]);
        $this->document($targetUserId)->update([
            ['path' => 'connectionIds', 'value' => FieldValue::arrayRemove([$currentUserId])],
        ]);
    }

    public function displayName(array $user, string $fallback = ''): string
    {
        $name = trim(implode(' ', array_filter([
            $user['firstName'] ?? '',
            $user['lastName'] ?? '',
        ])));

        if ($name !== '') {
            return $name;
        }

        return ($user['alias'] ?? '') ?: (($user['email'] ?? '') ?: $fallback);
    }

    private function resolveStatus(array $user): array
    {
        $status = $user['status'] ?? 'OFFLINE';
        $lastActiveAt = is_numeric($user['lastActiveAt'] ?? null) ? (int) $user['lastActiveAt'] : 0;

        if ($status === 'ONLINE' && $lastActiveAt > 0 && $this->firebase->nowMillis() - $lastActiveAt >= self::STANDBY_AFTER_MS) {
            $status = 'STANDBY';
            if (($user['id'] ?? '') !== '') {
                $this->document($user['id'])->set(['status' => $status], ['merge' => true]);
            }
        }

        $user['status'] = $status;

        return $user;
    }

    private function markRequestRead(string $userId, string $requestId): void
    {
        $this->userInboxDocument($userId, $requestId)->set(['read' => true], ['merge' => true]);
        $this->notificationDocument($requestId)->set(['read' => true], ['merge' => true]);
    }

    private function connectionRequestId(string $requesterId, string $targetUserId): string
    {
        return "connection_{$requesterId}_{$targetUserId}";
    }

    private function alias(array $user): string
    {
        $alias = $user['alias'] ?? '';

        if ($alias !== '') {
            return $alias;
        }

        return str((string) ($user['email'] ?? 'member'))->before('@')->toString() ?: 'member';
    }

    private function collection(): CollectionReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('users'));
    }

    private function document(string $userId): DocumentReference
    {
        return $this->collection()->document($userId);
    }

    private function userInboxDocument(string $userId, string $itemId): DocumentReference
    {
        return $this->document($userId)->collection('inbox')->document($itemId);
    }

    private function notificationDocument(string $notificationId): DocumentReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('notifications'))
            ->document($notificationId);
    }
}
