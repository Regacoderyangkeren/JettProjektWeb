<?php

namespace App\Services;

use App\Services\Firebase\FirebaseService;
use DomainException;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\FieldValue;
use RuntimeException;

class TaskService
{
    private const TODO = 'TODO';
    private const DONE = 'DONE';
    private const REVIEW = 'REVIEW';
    private const IN_REVIEW = 'in_review';
    private const APPROVED = 'approved';
    private const DISAPPROVED = 'disapproved';

    public function __construct(
        private readonly FirebaseService $firebase,
        private readonly ProjectService $projects,
    ) {
    }

    public function create(array $data, string $actorId): array
    {
        $collection = $this->collection();
        $id = $this->stringValue($data, 'id');
        $reference = $id !== '' ? $collection->document($id) : $collection->newDocument();
        $now = $this->firebase->nowMillis();
        $projectId = $this->stringValue($data, 'projectId');

        $this->projects->requireActive($projectId);

        $task = array_merge($this->defaultTask(), [
            'id' => $reference->id(),
            'title' => $this->stringValue($data, 'title'),
            'description' => $this->stringValue($data, 'description'),
            'assignedTo' => $this->stringValue($data, 'assignedTo'),
            'reviewerId' => $this->stringValue($data, 'reviewerId'),
            'createdBy' => $this->stringValue($data, 'createdBy') ?: $actorId,
            'projectId' => $projectId,
            'parentTaskId' => $this->stringValue($data, 'parentTaskId'),
            'type' => $this->enumValue($data, 'type', ['TODO_LIST', 'RESEARCH', 'ISSUE', 'IMPROVEMENT', 'REQUEST', 'REVIEW'], 'TODO_LIST'),
            'status' => $this->enumValue($data, 'status', ['TODO', 'IN_PROGRESS', 'DONE', 'CANCELLED'], self::TODO),
            'priority' => $this->enumValue($data, 'priority', ['LOW', 'MEDIUM', 'HIGH'], 'MEDIUM'),
            'pinned' => (bool) ($data['pinned'] ?? false),
            'priorityMarked' => (bool) ($data['priorityMarked'] ?? false),
            'checklistItems' => $this->listValue($data['checklistItems'] ?? []),
            'relatedTaskIds' => $this->uniqueStrings($data['relatedTaskIds'] ?? []),
            'resourceLinks' => $this->uniqueStrings($data['resourceLinks'] ?? []),
            'voteUserIds' => $this->uniqueStrings($data['voteUserIds'] ?? []),
            'attachments' => $this->listValue($data['attachments'] ?? []),
            'dueDate' => $this->intValue($data, 'dueDate'),
            'createdAt' => $this->intValue($data, 'createdAt') ?: $now,
            'updatedAt' => $now,
        ]);

        foreach ($this->openStringFields() as $field) {
            if (array_key_exists($field, $data)) {
                $task[$field] = $this->stringValue($data, $field);
            }
        }

        if (array_key_exists('votes', $data) && is_numeric($data['votes'])) {
            $task['votes'] = (int) $data['votes'];
        }

        $reference->set($task);

        return $task;
    }

    public function findOrFail(string $taskId): array
    {
        $snapshot = $this->document($taskId)->snapshot();

        if (! $snapshot->exists()) {
            throw new RuntimeException('Task not found.');
        }

        return $snapshot->data();
    }

    public function forProject(string $projectId): array
    {
        if ($projectId === '') {
            return [];
        }

        return $this->sortedDocuments(
            $this->collection()->where('projectId', '=', $projectId)->documents()
        );
    }

    public function forAssignee(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        return $this->sortedDocuments(
            $this->collection()->where('assignedTo', '=', $userId)->documents()
        );
    }

    public function workload(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        $items = [];

        foreach (['assignedTo', 'createdBy', 'reviewerId'] as $field) {
            foreach ($this->collection()->where($field, '=', $userId)->documents() as $document) {
                if ($document->exists()) {
                    $items[$document->id()] = $document->data();
                }
            }
        }

        return $this->sortItems(array_values($items));
    }

    public function update(string $taskId, array $updates): array
    {
        $task = $this->findOrFail($taskId);
        $this->projects->requireActive($task['projectId'] ?? '');

        $reserved = ['id', 'projectId', 'parentTaskId', 'createdBy', 'createdAt', 'updatedAt'];
        $safeUpdates = array_diff_key($updates, array_flip($reserved));

        foreach (['relatedTaskIds', 'resourceLinks', 'voteUserIds'] as $field) {
            if (array_key_exists($field, $safeUpdates)) {
                $safeUpdates[$field] = $this->uniqueStrings($safeUpdates[$field]);
            }
        }

        foreach (['checklistItems', 'attachments'] as $field) {
            if (array_key_exists($field, $safeUpdates)) {
                $safeUpdates[$field] = $this->listValue($safeUpdates[$field]);
            }
        }

        $safeUpdates['updatedAt'] = $this->firebase->nowMillis();
        $this->document($taskId)->set($safeUpdates, ['merge' => true]);

        return array_merge($task, $safeUpdates);
    }

    public function updateStatus(string $taskId, string $status): array
    {
        $task = $this->findOrFail($taskId);
        $this->projects->requireActive($task['projectId'] ?? '');

        if (! in_array($status, ['TODO', 'IN_PROGRESS', 'DONE', 'CANCELLED'], true)) {
            throw new DomainException('Unknown task status.');
        }

        $now = $this->firebase->nowMillis();
        $updates = [
            'status' => $status,
            'updatedAt' => $now,
        ];

        if ($status === self::DONE && ($task['type'] ?? '') !== self::REVIEW) {
            $projectTasks = $this->forProject($task['projectId'] ?? '');
            $blockedChildren = $this->unapprovedDescendants($task, $projectTasks);

            if ($blockedChildren !== []) {
                $count = count($blockedChildren);
                throw new DomainException("Approve $count subtask".($count === 1 ? '' : 's').' before marking this task done.');
            }

            $updates['reviewState'] = self::IN_REVIEW;
            $updates['reviewReason'] = '';
            $updates['reviewedAt'] = 0;
        }

        $this->document($taskId)->set($updates, ['merge' => true]);
        $nextTask = array_merge($task, $updates);

        if ($status === self::DONE && ($task['type'] ?? '') !== self::REVIEW) {
            $this->ensureReviewTask($nextTask, $projectTasks ?? []);
        }

        return $nextTask;
    }

    public function completeReview(string $reviewTaskId, bool $approved, string $reason): array
    {
        $reviewTask = $this->findOrFail($reviewTaskId);
        $parentTaskId = $this->stringValue($reviewTask, 'parentTaskId');

        if ($parentTaskId === '') {
            throw new DomainException('Review task does not have a parent task.');
        }

        $parentTask = $this->findOrFail($parentTaskId);
        $this->projects->requireActive($parentTask['projectId'] ?? $reviewTask['projectId'] ?? '');

        $now = $this->firebase->nowMillis();
        $updates = [
            'status' => $approved ? self::DONE : self::TODO,
            'reviewState' => $approved ? self::APPROVED : self::DISAPPROVED,
            'reviewReason' => $approved ? '' : trim($reason),
            'reviewedAt' => $now,
            'updatedAt' => $now,
        ];

        $this->document($reviewTaskId)->delete();
        $this->document($parentTaskId)->set($updates, ['merge' => true]);

        return array_merge($parentTask, $updates);
    }

    public function setPinned(string $taskId, bool $pinned): array
    {
        return $this->update($taskId, ['pinned' => $pinned]);
    }

    public function setPriorityMarked(string $taskId, bool $marked): array
    {
        return $this->update($taskId, [
            'priorityMarked' => $marked,
            'priority' => $marked ? 'HIGH' : 'MEDIUM',
        ]);
    }

    public function addAttachment(string $taskId, array $attachment): array
    {
        $task = $this->findOrFail($taskId);
        $this->projects->requireActive($task['projectId'] ?? '');
        $now = $this->firebase->nowMillis();

        $this->document($taskId)->update([
            ['path' => 'attachments', 'value' => FieldValue::arrayUnion([$attachment])],
            ['path' => 'updatedAt', 'value' => $now],
        ]);

        $task['attachments'] = array_values(array_merge($task['attachments'] ?? [], [$attachment]));
        $task['updatedAt'] = $now;

        return $task;
    }

    public function removeAttachment(string $taskId, array $attachment): array
    {
        $task = $this->findOrFail($taskId);
        $this->projects->requireActive($task['projectId'] ?? '');
        $now = $this->firebase->nowMillis();

        $this->document($taskId)->update([
            ['path' => 'attachments', 'value' => FieldValue::arrayRemove([$attachment])],
            ['path' => 'updatedAt', 'value' => $now],
        ]);

        $task['attachments'] = array_values(array_filter(
            $task['attachments'] ?? [],
            fn (mixed $existing): bool => $existing !== $attachment
        ));
        $task['updatedAt'] = $now;

        return $task;
    }

    public function delete(string $taskId): void
    {
        $task = $this->findOrFail($taskId);
        $this->projects->requireActive($task['projectId'] ?? '');
        $this->document($taskId)->delete();
    }

    private function ensureReviewTask(array $task, array $projectTasks): void
    {
        $reviewId = 'review_'.$task['id'];
        $reviewRef = $this->document($reviewId);

        if ($reviewRef->snapshot()->exists()) {
            return;
        }

        $now = $this->firebase->nowMillis();
        $reviewerId = $this->stringValue($task, 'reviewerId')
            ?: $this->stringValue($task, 'createdBy')
            ?: $this->stringValue($task, 'assignedTo');
        $reviewKind = $this->reviewKindFor($task, $projectTasks);
        $reviewTask = array_merge($this->defaultTask(), [
            'id' => $reviewId,
            'title' => $this->reviewKindLabel($reviewKind).': '.(($task['title'] ?? '') ?: 'Task'),
            'description' => 'Review whether this '.$this->reviewKindSubject($reviewKind).' needs improvement or can be fully closed.',
            'assignedTo' => $reviewerId,
            'reviewerId' => $reviewerId,
            'createdBy' => $this->stringValue($task, 'assignedTo') ?: $reviewerId,
            'projectId' => $this->stringValue($task, 'projectId'),
            'parentTaskId' => $this->stringValue($task, 'id'),
            'type' => self::REVIEW,
            'status' => self::TODO,
            'priority' => 'MEDIUM',
            'reviewKind' => $reviewKind,
            'attachments' => $task['attachments'] ?? [],
            'dueDate' => $now + (24 * 60 * 60 * 1000),
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);

        $reviewRef->set($reviewTask);

        if ($reviewerId !== '') {
            $this->createReviewInboxItem($task, $reviewTask, $reviewerId, $now);
        }
    }

    private function createReviewInboxItem(array $task, array $reviewTask, string $reviewerId, int $now): void
    {
        $item = [
            'id' => 'task_review_'.($task['id'] ?? '').'_'.$reviewerId,
            'userId' => $reviewerId,
            'type' => 'TASK_REVIEW',
            'title' => 'Task ready for review',
            'body' => '"'.(($task['title'] ?? '') ?: 'Task').'" is done and waiting for your review.',
            'projectId' => $this->stringValue($task, 'projectId'),
            'taskId' => $reviewTask['id'],
            'actorId' => $this->stringValue($task, 'assignedTo') ?: $this->stringValue($task, 'createdBy'),
            'createdAt' => $now,
        ];

        $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('users'))
            ->document($reviewerId)
            ->collection('inbox')
            ->document($item['id'])
            ->set($item, ['merge' => true]);

        $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('notifications'))
            ->document($item['id'])
            ->set($item, ['merge' => true]);
    }

    private function unapprovedDescendants(array $task, array $projectTasks): array
    {
        $childrenByParent = [];

        foreach ($projectTasks as $projectTask) {
            if (($projectTask['type'] ?? '') !== self::REVIEW) {
                $childrenByParent[$projectTask['parentTaskId'] ?? ''][] = $projectTask;
            }
        }

        $descendantsOf = function (string $taskId) use (&$descendantsOf, $childrenByParent): array {
            $directChildren = $childrenByParent[$taskId] ?? [];
            $descendants = $directChildren;

            foreach ($directChildren as $child) {
                $descendants = array_merge($descendants, $descendantsOf($child['id'] ?? ''));
            }

            return $descendants;
        };

        return array_values(array_filter(
            $descendantsOf($task['id'] ?? ''),
            fn (array $descendant): bool => ($descendant['reviewState'] ?? '') !== self::APPROVED
        ));
    }

    private function reviewKindFor(array $task, array $projectTasks): string
    {
        if (($task['parentTaskId'] ?? '') === '') {
            return 'review_task';
        }

        $parent = null;
        foreach ($projectTasks as $projectTask) {
            if (($projectTask['id'] ?? '') === ($task['parentTaskId'] ?? '')) {
                $parent = $projectTask;
                break;
            }
        }

        return ($parent['parentTaskId'] ?? '') === '' ? 'review_subtask' : 'review_nested_subtask';
    }

    private function reviewKindLabel(string $reviewKind): string
    {
        return match ($reviewKind) {
            'review_subtask' => 'Review subtask',
            'review_nested_subtask' => 'Review nested subtask',
            default => 'Review task',
        };
    }

    private function reviewKindSubject(string $reviewKind): string
    {
        return match ($reviewKind) {
            'review_subtask' => 'subtask',
            'review_nested_subtask' => 'nested subtask',
            default => 'task',
        };
    }

    private function collection(): CollectionReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('tasks'));
    }

    private function document(string $taskId): DocumentReference
    {
        return $this->collection()->document($taskId);
    }

    private function sortedDocuments(iterable $documents): array
    {
        $items = [];

        foreach ($documents as $document) {
            if ($document->exists()) {
                $items[] = $document->data();
            }
        }

        return $this->sortItems($items);
    }

    private function sortItems(array $items): array
    {
        usort($items, fn (array $left, array $right): int => ($right['updatedAt'] ?? 0) <=> ($left['updatedAt'] ?? 0));

        return $items;
    }

    private function defaultTask(): array
    {
        return [
            'id' => '',
            'title' => '',
            'description' => '',
            'assignedTo' => '',
            'reviewerId' => '',
            'createdBy' => '',
            'projectId' => '',
            'parentTaskId' => '',
            'type' => 'TODO_LIST',
            'status' => self::TODO,
            'priority' => 'MEDIUM',
            'pinned' => false,
            'priorityMarked' => false,
            'checklistItems' => [],
            'issueSeverity' => '',
            'issueType' => '',
            'reproductionSteps' => '',
            'expectedResult' => '',
            'actualResult' => '',
            'environment' => '',
            'currentState' => '',
            'proposedImprovement' => '',
            'benefit' => '',
            'impactLevel' => '',
            'beforeAfterPreview' => '',
            'requestedBy' => '',
            'requestType' => '',
            'reason' => '',
            'votes' => 0,
            'voteUserIds' => [],
            'feasibility' => '',
            'relatedTaskId' => '',
            'relatedTaskIds' => [],
            'researchQuestion' => '',
            'resourceLinks' => [],
            'findings' => '',
            'conclusion' => '',
            'decision' => '',
            'reviewKind' => '',
            'reviewState' => '',
            'reviewReason' => '',
            'reviewedAt' => 0,
            'attachments' => [],
            'dueDate' => 0,
            'createdAt' => 0,
            'updatedAt' => 0,
        ];
    }

    private function openStringFields(): array
    {
        return [
            'issueSeverity',
            'issueType',
            'reproductionSteps',
            'expectedResult',
            'actualResult',
            'environment',
            'currentState',
            'proposedImprovement',
            'benefit',
            'impactLevel',
            'beforeAfterPreview',
            'requestedBy',
            'requestType',
            'reason',
            'feasibility',
            'relatedTaskId',
            'researchQuestion',
            'findings',
            'conclusion',
            'decision',
            'reviewKind',
            'reviewState',
            'reviewReason',
        ];
    }

    private function stringValue(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }

    private function intValue(array $data, string $key): int
    {
        return is_numeric($data[$key] ?? null) ? (int) $data[$key] : 0;
    }

    private function enumValue(array $data, string $key, array $allowed, string $default): string
    {
        $value = strtoupper($this->stringValue($data, $key));

        return in_array($value, $allowed, true) ? $value : $default;
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

    private function listValue(mixed $values): array
    {
        return is_array($values) ? array_values($values) : [];
    }
}
