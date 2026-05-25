<?php

namespace App\Services;

use App\Services\Firebase\FirebaseService;
use DomainException;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use RuntimeException;

class ProjectService
{
    private const ACTIVE = 'ACTIVE';
    private const COMPLETED = 'COMPLETED';
    private const ARCHIVED = 'ARCHIVED';

    public function __construct(private readonly FirebaseService $firebase)
    {
    }

    public function create(array $data, string $actorId): array
    {
        $collection = $this->collection();
        $id = $this->stringValue($data, 'id');
        $reference = $id !== '' ? $collection->document($id) : $collection->newDocument();
        $now = $this->firebase->nowMillis();
        $ownerId = $this->stringValue($data, 'ownerId') ?: $actorId;
        $members = $this->uniqueStrings($data['memberIds'] ?? []);

        if ($ownerId !== '') {
            $members[] = $ownerId;
            $members = $this->uniqueStrings($members);
        }

        $project = [
            'id' => $reference->id(),
            'name' => $this->stringValue($data, 'name'),
            'description' => $this->stringValue($data, 'description'),
            'colorHex' => $this->stringValue($data, 'colorHex') ?: '#2F80ED',
            'ownerId' => $ownerId,
            'teamId' => $this->stringValue($data, 'teamId'),
            'teamName' => $this->stringValue($data, 'teamName'),
            'memberIds' => $members,
            'status' => self::ACTIVE,
            'startAt' => $this->intValue($data, 'startAt'),
            'endAt' => $this->intValue($data, 'endAt'),
            'createdAt' => $this->intValue($data, 'createdAt') ?: $now,
            'updatedAt' => $now,
        ];

        $reference->set($project);

        return $project;
    }

    public function findOrFail(string $projectId): array
    {
        $snapshot = $this->document($projectId)->snapshot();

        if (! $snapshot->exists()) {
            throw new RuntimeException('Project not found.');
        }

        return $snapshot->data();
    }

    public function forMember(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        return $this->sortedDocuments(
            $this->collection()->where('memberIds', 'array-contains', $userId)->documents()
        );
    }

    public function forOwner(string $ownerId): array
    {
        if ($ownerId === '') {
            return [];
        }

        return $this->sortedDocuments(
            $this->collection()->where('ownerId', '=', $ownerId)->documents()
        );
    }

    public function update(string $projectId, array $updates): array
    {
        $project = $this->requireActive($projectId);
        $safeUpdates = array_diff_key($updates, array_flip(['id', 'createdAt', 'updatedAt', 'status']));

        if (array_key_exists('memberIds', $safeUpdates)) {
            $safeUpdates['memberIds'] = $this->uniqueStrings($safeUpdates['memberIds']);
        }

        $safeUpdates['updatedAt'] = $this->firebase->nowMillis();
        $this->document($projectId)->set($safeUpdates, ['merge' => true]);

        return array_merge($project, $safeUpdates);
    }

    public function addMember(string $projectId, string $userId): array
    {
        $project = $this->requireActive($projectId);
        $members = $this->uniqueStrings(array_merge($project['memberIds'] ?? [], [$userId]));

        return $this->update($projectId, ['memberIds' => $members]);
    }

    public function removeMember(string $projectId, string $userId): array
    {
        $project = $this->requireActive($projectId);
        $members = array_values(array_filter(
            $this->uniqueStrings($project['memberIds'] ?? []),
            fn (string $memberId): bool => $memberId !== $userId
        ));

        return $this->update($projectId, ['memberIds' => $members]);
    }

    public function complete(string $projectId): array
    {
        $project = $this->requireActive($projectId);
        $tasks = $this->tasksForProject($projectId);
        $this->validateCanComplete($tasks);

        $updates = [
            'status' => self::COMPLETED,
            'updatedAt' => $this->firebase->nowMillis(),
        ];

        $this->document($projectId)->set($updates, ['merge' => true]);

        return array_merge($project, $updates);
    }

    public function archive(string $projectId): array
    {
        $project = $this->requireActive($projectId);
        $updates = [
            'status' => self::ARCHIVED,
            'updatedAt' => $this->firebase->nowMillis(),
        ];

        $this->document($projectId)->set($updates, ['merge' => true]);

        return array_merge($project, $updates);
    }

    public function delete(string $projectId): void
    {
        $this->requireActive($projectId);
        $this->document($projectId)->delete();
    }

    public function requireActive(string $projectId): array
    {
        if ($projectId === '') {
            return [];
        }

        $project = $this->findOrFail($projectId);

        if (($project['status'] ?? self::ACTIVE) !== self::ACTIVE) {
            throw new DomainException('Completed and archived projects are view only.');
        }

        return $project;
    }

    private function validateCanComplete(array $tasks): void
    {
        $projectTasks = array_values(array_filter(
            $tasks,
            fn (array $task): bool => ($task['type'] ?? '') !== 'REVIEW'
        ));

        if ($projectTasks === []) {
            throw new DomainException('Add and approve at least one task before completing this project.');
        }

        $unfinishedCount = count(array_filter(
            $projectTasks,
            fn (array $task): bool => ($task['status'] ?? '') !== 'DONE'
        ));
        $unapprovedCount = count(array_filter(
            $projectTasks,
            fn (array $task): bool => ($task['reviewState'] ?? '') !== 'approved'
        ));
        $pendingReviewCount = count(array_filter(
            $tasks,
            fn (array $task): bool => ($task['type'] ?? '') === 'REVIEW'
        ));

        if ($unfinishedCount > 0 || $unapprovedCount > 0 || $pendingReviewCount > 0) {
            $parts = [];
            if ($unfinishedCount > 0) {
                $parts[] = "$unfinishedCount unfinished";
            }
            if ($unapprovedCount > 0) {
                $parts[] = "$unapprovedCount not approved";
            }
            if ($pendingReviewCount > 0) {
                $parts[] = "$pendingReviewCount pending review";
            }

            throw new DomainException('Complete all tasks and approve every review first: '.implode(', ', $parts).'.');
        }
    }

    private function tasksForProject(string $projectId): array
    {
        return $this->sortedDocuments(
            $this->firebase
                ->firestore()
                ->collection($this->firebase->collectionName('tasks'))
                ->where('projectId', '=', $projectId)
                ->documents()
        );
    }

    private function collection(): CollectionReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('projects'));
    }

    private function document(string $projectId): DocumentReference
    {
        return $this->collection()->document($projectId);
    }

    private function sortedDocuments(iterable $documents): array
    {
        $items = [];

        foreach ($documents as $document) {
            if ($document->exists()) {
                $items[] = $document->data();
            }
        }

        usort($items, fn (array $left, array $right): int => ($right['updatedAt'] ?? 0) <=> ($left['updatedAt'] ?? 0));

        return $items;
    }

    private function stringValue(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }

    private function intValue(array $data, string $key): int
    {
        return is_numeric($data[$key] ?? null) ? (int) $data[$key] : 0;
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
}
