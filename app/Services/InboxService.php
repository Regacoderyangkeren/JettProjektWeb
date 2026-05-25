<?php

namespace App\Services;

use App\Services\Firebase\FirebaseService;
use RuntimeException;

class InboxService
{
    public function __construct(private readonly FirebaseService $firebase)
    {
    }

    public function list(string $userId, string $box = 'inbox', int $limit = 60): array
    {
        if ($userId === '') {
            return [];
        }

        $query = $this->inboxCollection($userId, $box)
            ->orderBy('createdAt', 'DESC')
            ->limit(max(1, min($limit, 100)));
        $items = [];

        foreach ($query->documents() as $document) {
            if ($document->exists()) {
                $items[] = $document->data();
            }
        }

        return $items;
    }

    public function create(array $data, string $actorId): array
    {
        $userId = $this->stringValue($data, 'userId') ?: $actorId;
        $box = $this->stringValue($data, 'box') === 'connection' ? 'connectionInbox' : 'inbox';
        $reference = $this->inboxCollection($userId, $box)->newDocument();
        $id = $this->stringValue($data, 'id') ?: $reference->id();
        $item = [
            'id' => $id,
            'userId' => $userId,
            'type' => $this->stringValue($data, 'type') ?: 'system',
            'title' => $this->stringValue($data, 'title'),
            'body' => $this->stringValue($data, 'body'),
            'teamId' => $this->stringValue($data, 'teamId'),
            'projectId' => $this->stringValue($data, 'projectId'),
            'taskId' => $this->stringValue($data, 'taskId'),
            'inviteId' => $this->stringValue($data, 'inviteId'),
            'messageId' => $this->stringValue($data, 'messageId'),
            'actorId' => $this->stringValue($data, 'actorId') ?: $actorId,
            'read' => (bool) ($data['read'] ?? false),
            'createdAt' => is_numeric($data['createdAt'] ?? null) ? (int) $data['createdAt'] : $this->firebase->nowMillis(),
        ];

        $this->inboxCollection($userId, $box)->document($id)->set($item, ['merge' => true]);
        $this->notificationDocument($id)->set($item, ['merge' => true]);

        return $item;
    }

    public function markRead(string $userId, string $itemId, string $box = 'inbox', ?string $notificationId = null): void
    {
        if ($userId === '' || $itemId === '') {
            throw new RuntimeException('Inbox item not found.');
        }

        $this->inboxCollection($userId, $box)
            ->document($itemId)
            ->set(['read' => true], ['merge' => true]);

        $this->notificationDocument($notificationId ?: $itemId)
            ->set(['read' => true], ['merge' => true]);
    }

    public function delete(string $userId, string $itemId, string $box = 'inbox'): void
    {
        if ($userId === '' || $itemId === '') {
            throw new RuntimeException('Inbox item not found.');
        }

        $this->inboxCollection($userId, $box)->document($itemId)->delete();
    }

    private function inboxCollection(string $userId, string $box)
    {
        $collection = $box === 'connection' || $box === 'connectionInbox'
            ? 'connectionInbox'
            : 'inbox';

        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('users'))
            ->document($userId)
            ->collection($collection);
    }

    private function notificationDocument(string $notificationId)
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('notifications'))
            ->document($notificationId);
    }

    private function stringValue(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }
}
