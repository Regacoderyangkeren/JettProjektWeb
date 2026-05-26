<?php

namespace App\Services;

use App\Services\Firebase\FirebaseService;
use DomainException;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\FieldValue;
use Google\Cloud\Firestore\Transaction;
use RuntimeException;

class TeamService
{
    private const PENDING = 'pending';

    private const ACCEPTED = 'accepted';

    private const DECLINED = 'declined';

    private const RESERVED_TAG_IDS = ['leader', 'member'];

    private const TAG_PERMISSION_KEYS = [
        'project.create',
        'project.read',
        'project.update',
        'project.delete',
        'project.setting.update',
        'task.create',
        'task.read',
        'task.update',
        'task.delete',
        'task.todo.create',
        'task.research.create',
        'task.issue.create',
        'task.improvement.create',
        'task.request.create',
        'task.assign_member',
        'task.assign_reviewer',
        'task.set_priority',
        'task.pin',
        'task.mark_done',
        'task.review.resolve',
        'task.checklist.update',
        'task_attachment.create',
        'task_attachment.delete',
        'subtask.create',
        'subtask.update',
        'subtask.delete',
        'calendar.read',
        'gantt.create',
        'gantt.read',
        'gantt.update',
        'gantt.delete',
        'tag.create',
        'tag.read',
        'tag.update',
        'tag.delete',
        'tag.assign',
        'chat.read',
        'chat.send',
        'chat.edit_own',
        'chat.edit_any',
        'chat.delete_own',
        'chat.delete_any',
        'chat.react',
        'chat.pin',
        'chat.attach_file',
        'chat.attach_media',
    ];

    public function __construct(private readonly FirebaseService $firebase) {}

    public function forMember(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        $teams = [];

        foreach ($this->collection()->where('memberIds', 'array-contains', $userId)->documents() as $document) {
            if ($document->exists()) {
                $teams[] = $document->data();
            }
        }

        usort($teams, fn (array $left, array $right): int => ($right['updatedAt'] ?? 0) <=> ($left['updatedAt'] ?? 0));

        return $teams;
    }

    public function ledBy(string $userId): array
    {
        return array_values(array_filter(
            $this->forMember($userId),
            fn (array $team): bool => ($team['leaderId'] ?? '') === $userId
        ));
    }

    public function connectedUsers(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        $user = $this->userOrFail($userId);
        $connected = [];

        foreach ($this->uniqueStrings($user['connectionIds'] ?? []) as $connectionId) {
            $snapshot = $this->userDocument($connectionId)->snapshot();
            if ($snapshot->exists()) {
                $connected[] = $snapshot->data();
            }
        }

        usort($connected, fn (array $left, array $right): int => strcasecmp($this->displayName($left), $this->displayName($right)));

        return $connected;
    }

    public function create(array $data, string $leaderId): array
    {
        $name = $this->stringValue($data, 'name');
        if ($name === '') {
            throw new DomainException('Team name is required.');
        }

        $leader = $this->userOrFail($leaderId);
        $teammates = [];

        foreach ($this->uniqueStrings($data['teammateIds'] ?? []) as $teammateId) {
            if ($teammateId !== $leaderId) {
                $teammates[$teammateId] = $this->userOrFail($teammateId);
            }
        }

        $reference = $this->collection()->newDocument();
        $now = $this->firebase->nowMillis();
        $team = [
            'id' => $reference->id(),
            'name' => $name,
            'description' => $this->stringValue($data, 'description'),
            'leaderId' => $leaderId,
            'leaderName' => $this->displayName($leader),
            'leaderProfilePictureUrl' => $this->stringValue($leader, 'profilePictureUrl'),
            'memberIds' => [$leaderId],
            'pendingMemberIds' => array_keys($teammates),
            'memberJoinedAt' => [$leaderId => $now],
            'memberTags' => [$leaderId => ['leader']],
            'inviteCode' => $this->stringValue($data, 'inviteCode') ?: $reference->id(),
            'createdAt' => $now,
            'updatedAt' => $now,
        ];

        $this->firebase->firestore()->runTransaction(function (Transaction $transaction) use ($reference, $team, $leader, $teammates, $now): void {
            $transaction->create($reference, $team);
            $transaction->create($reference->collection('activity')->newDocument(), [
                'teamId' => $team['id'],
                'actorId' => $team['leaderId'],
                'actorName' => $team['leaderName'],
                'title' => 'Created team',
                'description' => 'Created '.$team['name'],
                'createdAt' => $now,
            ]);

            foreach ($teammates as $teammate) {
                $this->writeInvite($transaction, $team, $leader, $teammate, $now);
            }
        });

        return $team;
    }

    public function findOrFail(string $teamId): array
    {
        $snapshot = $this->document($teamId)->snapshot();

        if (! $snapshot->exists()) {
            throw new RuntimeException('Team not found.');
        }

        return $snapshot->data();
    }

    public function detail(string $teamId, string $currentUserId): array
    {
        $team = $this->requireMember($teamId, $currentUserId);
        $tags = $this->tags($teamId);
        $tagsById = [];

        foreach ($tags as $tag) {
            $tagsById[$tag['id'] ?? ''] = $tag;
        }

        $members = [];

        foreach ($this->uniqueStrings($team['memberIds'] ?? []) as $memberId) {
            $snapshot = $this->userDocument($memberId)->snapshot();
            $fallbackTag = $memberId === ($team['leaderId'] ?? '') ? 'leader' : 'member';
            $tagIds = $this->uniqueStrings($team['memberTags'][$memberId] ?? [$fallbackTag]);
            if ($tagIds === []) {
                $tagIds = [$fallbackTag];
            }

            $members[] = [
                'user' => $snapshot->exists() ? $snapshot->data() : ['id' => $memberId],
                'joinedAt' => (int) ($team['memberJoinedAt'][$memberId] ?? $team['createdAt'] ?? 0),
                'tags' => array_map(fn (string $tagId): string => (string) ($tagsById[$tagId]['name'] ?? $tagId), $tagIds),
                'tagIds' => $tagIds,
                'tagColors' => array_map(fn (string $tagId): string => (string) ($tagsById[$tagId]['colorHex'] ?? $this->defaultTagColor($tagId)), $tagIds),
            ];
        }

        return [
            'team' => $team,
            'members' => $members,
            'tags' => $tags,
            'pendingInvites' => ($team['leaderId'] ?? '') === $currentUserId ? $this->pendingInvitesForTeam($teamId) : [],
            'inviteCandidates' => ($team['leaderId'] ?? '') === $currentUserId ? $this->inviteCandidates($team, $currentUserId) : [],
        ];
    }

    public function createTag(string $teamId, string $leaderId, array $data): array
    {
        $team = $this->requireLeader($teamId, $leaderId);
        $name = $this->requiredTagName($data);
        $tagId = $this->tagIdFromName($name);

        if (in_array($tagId, self::RESERVED_TAG_IDS, true)) {
            throw new DomainException('That tag is reserved.');
        }

        $this->assertUniqueTag($teamId, $tagId, $name);
        $now = $this->firebase->nowMillis();
        $tag = [
            'id' => $tagId,
            'teamId' => $teamId,
            'name' => $name,
            'colorHex' => $this->tagColor($data['colorHex'] ?? null),
            'permissions' => $this->defaultTagPermissions(),
            'createdBy' => $leaderId,
            'createdAt' => $now,
            'updatedAt' => $now,
            'system' => false,
        ];

        $this->tagDocument($teamId, $tagId)->set($tag);
        $this->storeTagAssignments($team, $tagId, $this->uniqueStrings($data['assignedMemberIds'] ?? []), $now);
        $this->recordTagActivity($team, 'Created tag', 'created tag '.$name, $now);

        return $tag;
    }

    public function updateTag(string $teamId, string $leaderId, string $tagId, array $data): array
    {
        $team = $this->requireLeader($teamId, $leaderId);
        $tagReference = $this->tagDocument($teamId, $tagId);
        $snapshot = $tagReference->snapshot();

        if (! $snapshot->exists()) {
            throw new RuntimeException('Tag not found.');
        }

        $tag = $snapshot->data();
        if (($tag['system'] ?? false) || in_array($tagId, self::RESERVED_TAG_IDS, true)) {
            throw new DomainException('System tags cannot be edited here.');
        }

        $name = $this->requiredTagName($data);
        $this->assertUniqueTag($teamId, $tagId, $name, $tagId);
        $now = $this->firebase->nowMillis();
        $updatedTag = array_merge($tag, [
            'name' => $name,
            'colorHex' => $this->tagColor($data['colorHex'] ?? null),
            'updatedAt' => $now,
        ]);

        $tagReference->set($updatedTag, ['merge' => true]);
        $this->storeTagAssignments($team, $tagId, $this->uniqueStrings($data['assignedMemberIds'] ?? []), $now);
        $this->recordTagActivity($team, 'Updated tag', 'updated tag '.$name, $now);

        return $updatedTag;
    }

    public function incomingInvites(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        $invites = [];
        foreach ($this->inviteCollection()->where('invitedUserId', '=', $userId)->documents() as $document) {
            if ($document->exists()) {
                $invite = $document->data();
                if (($invite['status'] ?? self::PENDING) === self::PENDING) {
                    $invites[] = $invite;
                }
            }
        }

        usort($invites, fn (array $left, array $right): int => ($right['createdAt'] ?? 0) <=> ($left['createdAt'] ?? 0));

        return $invites;
    }

    public function invite(string $teamId, string $inviterId, string $invitedUserId): array
    {
        if ($invitedUserId === '' || $invitedUserId === $inviterId) {
            throw new DomainException('Select a teammate to invite.');
        }

        $inviter = $this->userOrFail($inviterId);
        $invitedUser = $this->userOrFail($invitedUserId);
        $teamReference = $this->document($teamId);
        $now = $this->firebase->nowMillis();

        return $this->firebase->firestore()->runTransaction(function (Transaction $transaction) use ($teamReference, $inviterId, $inviter, $invitedUserId, $invitedUser, $now): array {
            $snapshot = $transaction->snapshot($teamReference);
            if (! $snapshot->exists()) {
                throw new RuntimeException('Team not found.');
            }

            $team = $snapshot->data();
            $this->assertLeader($team, $inviterId);

            if (in_array($invitedUserId, $team['memberIds'] ?? [], true)) {
                throw new DomainException('This user is already a member.');
            }
            if (in_array($invitedUserId, $team['pendingMemberIds'] ?? [], true)) {
                throw new DomainException('This user already has a pending invite.');
            }

            $transaction->update($teamReference, [
                ['path' => 'pendingMemberIds', 'value' => FieldValue::arrayUnion([$invitedUserId])],
                ['path' => 'updatedAt', 'value' => $now],
            ]);

            return $this->writeInvite($transaction, $team, $inviter, $invitedUser, $now);
        });
    }

    public function acceptInvite(string $inviteId, string $userId): string
    {
        $user = $this->userOrFail($userId);
        $inviteReference = $this->inviteDocument($inviteId);
        $now = $this->firebase->nowMillis();

        return $this->firebase->firestore()->runTransaction(function (Transaction $transaction) use ($inviteReference, $inviteId, $userId, $user, $now): string {
            $inviteSnapshot = $transaction->snapshot($inviteReference);
            if (! $inviteSnapshot->exists()) {
                throw new RuntimeException('Invite not found.');
            }

            $invite = $inviteSnapshot->data();
            $this->assertInviteRecipient($invite, $userId);

            if (($invite['status'] ?? self::PENDING) !== self::PENDING) {
                throw new DomainException('This invite is no longer pending.');
            }

            $teamReference = $this->document((string) ($invite['teamId'] ?? ''));
            $teamSnapshot = $transaction->snapshot($teamReference);
            if (! $teamSnapshot->exists()) {
                throw new RuntimeException('Team not found.');
            }

            $team = $teamSnapshot->data();
            $transaction->update($teamReference, [
                ['path' => 'memberIds', 'value' => FieldValue::arrayUnion([$userId])],
                ['path' => 'pendingMemberIds', 'value' => FieldValue::arrayRemove([$userId])],
                ['path' => "memberTags.$userId", 'value' => ['member']],
                ['path' => "memberJoinedAt.$userId", 'value' => $now],
                ['path' => 'updatedAt', 'value' => $now],
            ]);
            $transaction->update($inviteReference, [
                ['path' => 'status', 'value' => self::ACCEPTED],
                ['path' => 'respondedAt', 'value' => $now],
            ]);
            $transaction->set($this->inboxDocument($userId, $inviteId), ['read' => true], ['merge' => true]);
            $transaction->set($this->notificationDocument($inviteId), ['read' => true], ['merge' => true]);
            $transaction->create($teamReference->collection('activity')->newDocument(), [
                'teamId' => $team['id'] ?? ($invite['teamId'] ?? ''),
                'actorId' => $userId,
                'actorName' => $this->displayName($user),
                'title' => 'Joined team',
                'description' => $this->displayName($user).' joined '.($team['name'] ?? 'the team'),
                'createdAt' => $now,
            ]);

            return (string) ($invite['teamId'] ?? '');
        });
    }

    public function declineInvite(string $inviteId, string $userId): void
    {
        $inviteReference = $this->inviteDocument($inviteId);
        $now = $this->firebase->nowMillis();

        $this->firebase->firestore()->runTransaction(function (Transaction $transaction) use ($inviteReference, $inviteId, $userId, $now): void {
            $inviteSnapshot = $transaction->snapshot($inviteReference);
            if (! $inviteSnapshot->exists()) {
                throw new RuntimeException('Invite not found.');
            }

            $invite = $inviteSnapshot->data();
            $this->assertInviteRecipient($invite, $userId);
            if (($invite['status'] ?? self::PENDING) !== self::PENDING) {
                return;
            }

            $transaction->update($this->document((string) ($invite['teamId'] ?? '')), [
                ['path' => 'pendingMemberIds', 'value' => FieldValue::arrayRemove([$userId])],
                ['path' => 'updatedAt', 'value' => $now],
            ]);
            $transaction->update($inviteReference, [
                ['path' => 'status', 'value' => self::DECLINED],
                ['path' => 'respondedAt', 'value' => $now],
            ]);
            $transaction->set($this->inboxDocument($userId, $inviteId), ['read' => true], ['merge' => true]);
            $transaction->set($this->notificationDocument($inviteId), ['read' => true], ['merge' => true]);
        });
    }

    public function removeMember(string $teamId, string $leaderId, string $memberId): void
    {
        $team = $this->requireLeader($teamId, $leaderId);
        if ($memberId === '' || $memberId === ($team['leaderId'] ?? '')) {
            throw new DomainException('Leader cannot remove themselves.');
        }

        $this->document($teamId)->update([
            ['path' => 'memberIds', 'value' => FieldValue::arrayRemove([$memberId])],
            ['path' => "memberTags.$memberId", 'value' => FieldValue::deleteField()],
            ['path' => "memberJoinedAt.$memberId", 'value' => FieldValue::deleteField()],
            ['path' => 'updatedAt', 'value' => $this->firebase->nowMillis()],
        ]);
    }

    public function leave(string $teamId, string $userId): void
    {
        $team = $this->requireMember($teamId, $userId);
        if (($team['leaderId'] ?? '') === $userId) {
            throw new DomainException('Transfer leadership before leaving.');
        }

        $this->document($teamId)->update([
            ['path' => 'memberIds', 'value' => FieldValue::arrayRemove([$userId])],
            ['path' => "memberTags.$userId", 'value' => FieldValue::deleteField()],
            ['path' => "memberJoinedAt.$userId", 'value' => FieldValue::deleteField()],
            ['path' => 'updatedAt', 'value' => $this->firebase->nowMillis()],
        ]);
    }

    public function delete(string $teamId, string $leaderId): void
    {
        $this->requireLeader($teamId, $leaderId);
        $this->document($teamId)->delete();
    }

    public function requireLeader(string $teamId, string $userId): array
    {
        $team = $this->findOrFail($teamId);
        $this->assertLeader($team, $userId);

        return $team;
    }

    private function requireMember(string $teamId, string $userId): array
    {
        $team = $this->findOrFail($teamId);
        if (! in_array($userId, $team['memberIds'] ?? [], true)) {
            throw new DomainException('You are not a member of this team.');
        }

        return $team;
    }

    private function pendingInvitesForTeam(string $teamId): array
    {
        $invites = [];
        foreach ($this->inviteCollection()->where('teamId', '=', $teamId)->documents() as $document) {
            if ($document->exists()) {
                $invite = $document->data();
                if (($invite['status'] ?? self::PENDING) === self::PENDING) {
                    $invites[] = $invite;
                }
            }
        }

        usort($invites, fn (array $left, array $right): int => ($right['createdAt'] ?? 0) <=> ($left['createdAt'] ?? 0));

        return $invites;
    }

    private function inviteCandidates(array $team, string $leaderId): array
    {
        $excludedIds = array_merge($team['memberIds'] ?? [], $team['pendingMemberIds'] ?? []);

        return array_values(array_filter(
            $this->connectedUsers($leaderId),
            fn (array $user): bool => ! in_array($user['id'] ?? '', $excludedIds, true)
        ));
    }

    private function tags(string $teamId): array
    {
        $tags = [];

        foreach ($this->document($teamId)->collection('tags')->documents() as $document) {
            if ($document->exists()) {
                $tags[] = $document->data();
            }
        }

        usort($tags, fn (array $left, array $right): int => ((! ($left['system'] ?? false)) <=> (! ($right['system'] ?? false))) ?: strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')));

        return $tags;
    }

    private function storeTagAssignments(array $team, string $tagId, array $assignedMemberIds, int $now): void
    {
        $memberIds = $this->uniqueStrings($team['memberIds'] ?? []);
        $assignedMemberIds = array_values(array_intersect($assignedMemberIds, $memberIds));
        $memberTags = is_array($team['memberTags'] ?? null) ? $team['memberTags'] : [];

        foreach ($memberIds as $memberId) {
            $fallbackTag = $memberId === ($team['leaderId'] ?? '') ? 'leader' : 'member';
            $tagIds = $this->uniqueStrings($memberTags[$memberId] ?? [$fallbackTag]);
            $tagIds = array_values(array_filter($tagIds, fn (string $id): bool => $id !== $tagId));

            if (in_array($memberId, $assignedMemberIds, true)) {
                $tagIds[] = $tagId;
            }

            if (! in_array($fallbackTag, $tagIds, true)) {
                $tagIds[] = $fallbackTag;
            }

            $memberTags[$memberId] = array_values(array_unique($tagIds));
        }

        $this->document((string) $team['id'])->set([
            'memberTags' => $memberTags,
            'updatedAt' => $now,
        ], ['merge' => true]);
    }

    private function recordTagActivity(array $team, string $title, string $description, int $now): void
    {
        $this->document((string) $team['id'])->collection('activity')->newDocument()->set([
            'teamId' => $team['id'],
            'actorId' => $team['leaderId'] ?? '',
            'actorName' => $team['leaderName'] ?? 'Leader',
            'title' => $title,
            'description' => ($team['leaderName'] ?? 'Leader').' '.$description,
            'createdAt' => $now,
        ]);
    }

    private function assertUniqueTag(string $teamId, string $tagId, string $name, ?string $ignoreTagId = null): void
    {
        foreach ($this->tags($teamId) as $existing) {
            $existingId = (string) ($existing['id'] ?? '');
            if ($ignoreTagId !== null && $existingId === $ignoreTagId) {
                continue;
            }

            if ($existingId === $tagId || strcasecmp((string) ($existing['name'] ?? ''), $name) === 0) {
                throw new DomainException('Tag already exists.');
            }
        }
    }

    private function writeInvite(Transaction $transaction, array $team, array $inviter, array $invitedUser, int $now): array
    {
        $reference = $this->inviteCollection()->newDocument();
        $invite = [
            'id' => $reference->id(),
            'teamId' => $team['id'],
            'teamName' => $team['name'],
            'inviterId' => $inviter['id'],
            'inviterName' => $this->displayName($inviter),
            'invitedUserId' => $invitedUser['id'],
            'invitedAlias' => $invitedUser['alias'] ?? '',
            'status' => self::PENDING,
            'createdAt' => $now,
            'respondedAt' => 0,
        ];
        $inboxItem = [
            'id' => $reference->id(),
            'userId' => $invitedUser['id'],
            'type' => 'team_invite',
            'title' => 'Team invite',
            'body' => $this->displayName($inviter).' invited you to join '.$team['name'],
            'teamId' => $team['id'],
            'projectId' => '',
            'taskId' => '',
            'inviteId' => $reference->id(),
            'messageId' => '',
            'actorId' => $inviter['id'],
            'read' => false,
            'createdAt' => $now,
        ];

        $transaction->create($reference, $invite);
        $transaction->set($this->inboxDocument((string) $invitedUser['id'], $reference->id()), $inboxItem);
        $transaction->set($this->notificationDocument($reference->id()), $inboxItem);

        return $invite;
    }

    private function assertLeader(array $team, string $userId): void
    {
        if (($team['leaderId'] ?? '') !== $userId) {
            throw new DomainException('Only the leader can manage this team.');
        }
    }

    private function assertInviteRecipient(array $invite, string $userId): void
    {
        if (($invite['invitedUserId'] ?? '') !== $userId) {
            throw new DomainException('This invite belongs to another user.');
        }
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

    private function stringValue(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }

    private function requiredTagName(array $data): string
    {
        $name = $this->stringValue($data, 'name');

        if ($name === '') {
            throw new DomainException('Tag name is required.');
        }

        return $name;
    }

    private function tagIdFromName(string $name): string
    {
        return str($name)->lower()->replaceMatches('/[^a-z0-9_-]+/', '-')->trim('-')->toString()
            ?: 'tag-'.$this->firebase->nowMillis();
    }

    private function tagColor(mixed $color): string
    {
        $color = is_string($color) ? strtoupper(trim($color)) : '';

        return preg_match('/^#[0-9A-F]{6}$/', $color) === 1 ? $color : '#6C5CE7';
    }

    private function defaultTagColor(string $tagId): string
    {
        return match ($tagId) {
            'leader' => '#2F80ED',
            'member' => '#27AE60',
            'project-planner' => '#2D9CDB',
            'task-manager' => '#9B51E0',
            'task-contributor' => '#00A896',
            'reviewer' => '#F2994A',
            'researcher' => '#00A8A8',
            'chat-moderator' => '#F06795',
            'viewer' => '#64748B',
            default => '#6C5CE7',
        };
    }

    private function defaultTagPermissions(): array
    {
        return array_fill_keys(self::TAG_PERMISSION_KEYS, false);
    }

    private function uniqueStrings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $value): string => is_string($value) ? trim($value) : '', $values),
            fn (string $value): bool => $value !== ''
        )));
    }

    private function collection(): CollectionReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('teams'));
    }

    private function document(string $teamId): DocumentReference
    {
        return $this->collection()->document($teamId);
    }

    private function inviteCollection(): CollectionReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('team_invites'));
    }

    private function inviteDocument(string $inviteId): DocumentReference
    {
        return $this->inviteCollection()->document($inviteId);
    }

    private function tagDocument(string $teamId, string $tagId): DocumentReference
    {
        return $this->document($teamId)->collection('tags')->document($tagId);
    }

    private function userDocument(string $userId): DocumentReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('users'))
            ->document($userId);
    }

    private function inboxDocument(string $userId, string $itemId): DocumentReference
    {
        return $this->userDocument($userId)->collection('inbox')->document($itemId);
    }

    private function notificationDocument(string $itemId): DocumentReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('notifications'))
            ->document($itemId);
    }
}
