<?php

namespace App\Services;

use App\Services\Firebase\FirebaseService;
use DomainException;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use RuntimeException;

class ChatService
{
    private const MESSAGE_LIMIT = 120;

    public function __construct(private readonly FirebaseService $firebase) {}

    public function connectionThread(string $currentUserId, string $targetUserId): array
    {
        [$currentUser, $targetUser] = $this->connectedUsers($currentUserId, $targetUserId);
        $chatId = $this->connectionChatId($currentUserId, $targetUserId);
        $thread = $this->connectionDocument($chatId);

        if ($thread->snapshot()->exists()) {
            $this->markConnectionRead($currentUserId, $chatId);
        }

        return [
            'currentUser' => $currentUser,
            'targetUser' => $targetUser,
            'messages' => $this->messages($thread->collection('messages')),
        ];
    }

    public function sendConnectionMessage(string $currentUserId, string $targetUserId, string $body): array
    {
        [$sender, $targetUser] = $this->connectedUsers($currentUserId, $targetUserId);
        $cleanBody = $this->requiredBody($body);
        $chatId = $this->connectionChatId($currentUserId, $targetUserId);
        $thread = $this->connectionDocument($chatId);
        $messageReference = $thread->collection('messages')->newDocument();
        $now = $this->firebase->nowMillis();
        $message = $this->message($messageReference->id(), $chatId, $sender, $cleanBody, $now);
        $inboxItem = [
            'id' => $chatId,
            'userId' => $targetUserId,
            'type' => 'connection_chat',
            'title' => $this->displayName($sender),
            'body' => mb_substr($cleanBody, 0, 160),
            'teamId' => '',
            'projectId' => '',
            'taskId' => '',
            'inviteId' => '',
            'messageId' => $messageReference->id(),
            'actorId' => $currentUserId,
            'read' => false,
            'createdAt' => $now,
        ];

        $messageReference->set($message);
        $thread->set([
            'participantIds' => $this->sortedIds([$currentUserId, $targetUserId]),
            'updatedAt' => $now,
            'lastMessage' => mb_substr($cleanBody, 0, 160),
            'lastSenderId' => $currentUserId,
        ], ['merge' => true]);
        $this->userDocument($targetUserId)->collection('connectionInbox')->document($chatId)->set($inboxItem);
        $this->notificationDocument("connection_$chatId")->set(array_merge($inboxItem, [
            'id' => "connection_$chatId",
        ]));

        return $message;
    }

    public function teamThread(string $teamId, string $currentUserId): array
    {
        $team = $this->teamForMember($teamId, $currentUserId);

        return [
            'team' => $team,
            'currentUserId' => $currentUserId,
            'messages' => $this->messages($this->teamDocument($teamId)->collection('messages')),
        ];
    }

    public function sendTeamMessage(string $teamId, string $currentUserId, string $body): array
    {
        $this->teamForMember($teamId, $currentUserId);
        $sender = $this->userOrFail($currentUserId);
        $cleanBody = $this->requiredBody($body);
        $team = $this->teamDocument($teamId);
        $messageReference = $team->collection('messages')->newDocument();
        $now = $this->firebase->nowMillis();
        $message = $this->message($messageReference->id(), $teamId, $sender, $cleanBody, $now);

        $messageReference->set($message);
        $team->set(['updatedAt' => $now], ['merge' => true]);

        return $message;
    }

    private function connectedUsers(string $currentUserId, string $targetUserId): array
    {
        if ($currentUserId === '' || $targetUserId === '' || $currentUserId === $targetUserId) {
            throw new DomainException('Invalid connection chat.');
        }

        $currentUser = $this->userOrFail($currentUserId);
        if (! in_array($targetUserId, $currentUser['connectionIds'] ?? [], true)) {
            throw new DomainException('Connect first to chat.');
        }

        return [$currentUser, $this->userOrFail($targetUserId)];
    }

    private function teamForMember(string $teamId, string $currentUserId): array
    {
        $snapshot = $this->teamDocument($teamId)->snapshot();
        if (! $snapshot->exists()) {
            throw new RuntimeException('Team not found.');
        }

        $team = $snapshot->data();
        if (! in_array($currentUserId, $team['memberIds'] ?? [], true)) {
            throw new DomainException('You are not a member of this team.');
        }

        return $team;
    }

    private function messages(CollectionReference $collection): array
    {
        $messages = [];

        foreach ($collection->orderBy('createdAt', 'ASC')->limit(self::MESSAGE_LIMIT)->documents() as $document) {
            if ($document->exists()) {
                $messages[] = $document->data();
            }
        }

        return $messages;
    }

    private function message(string $id, string $threadId, array $sender, string $body, int $now): array
    {
        return [
            'id' => $id,
            'teamId' => $threadId,
            'senderId' => $sender['id'],
            'senderName' => $this->displayName($sender),
            'senderProfilePictureUrl' => $sender['profilePictureUrl'] ?? '',
            'body' => $body,
            'attachmentUrl' => '',
            'attachmentName' => '',
            'attachmentType' => '',
            'attachments' => [],
            'emote' => '',
            'replyToMessageId' => '',
            'replyToSenderId' => '',
            'replyToSenderName' => '',
            'replyToSenderProfilePictureUrl' => '',
            'replyToPreview' => '',
            'reactions' => [],
            'editedAt' => 0,
            'createdAt' => $now,
        ];
    }

    private function requiredBody(string $body): string
    {
        $body = trim($body);

        if ($body === '') {
            throw new DomainException('Write a message first.');
        }

        return $body;
    }

    private function markConnectionRead(string $userId, string $chatId): void
    {
        $this->userDocument($userId)->collection('connectionInbox')->document($chatId)->set([
            'read' => true,
        ], ['merge' => true]);
        $this->notificationDocument("connection_$chatId")->set([
            'read' => true,
        ], ['merge' => true]);
    }

    private function userOrFail(string $userId): array
    {
        $snapshot = $this->userDocument($userId)->snapshot();
        if (! $snapshot->exists()) {
            throw new RuntimeException('User not found.');
        }

        return $snapshot->data();
    }

    private function displayName(array $user): string
    {
        $name = trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? ''));

        return $name !== '' ? $name : (($user['alias'] ?? '') ?: ($user['email'] ?? 'Member'));
    }

    private function connectionChatId(string $firstUserId, string $secondUserId): string
    {
        return implode('_', $this->sortedIds([$firstUserId, $secondUserId]));
    }

    private function sortedIds(array $ids): array
    {
        sort($ids);

        return $ids;
    }

    private function connectionDocument(string $chatId): DocumentReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('connection_chats'))
            ->document($chatId);
    }

    private function teamDocument(string $teamId): DocumentReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('teams'))
            ->document($teamId);
    }

    private function userDocument(string $userId): DocumentReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('users'))
            ->document($userId);
    }

    private function notificationDocument(string $notificationId): DocumentReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('notifications'))
            ->document($notificationId);
    }
}
