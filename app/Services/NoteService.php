<?php

namespace App\Services;

use App\Services\Firebase\FirebaseService;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use RuntimeException;

class NoteService
{
    public function __construct(private readonly FirebaseService $firebase)
    {
    }

    public function save(array $data, string $actorId): array
    {
        $collection = $this->collection();
        $id = $this->stringValue($data, 'id');
        $reference = $id !== '' ? $collection->document($id) : $collection->newDocument();

        $note = [
            'id' => $reference->id(),
            'userId' => $this->stringValue($data, 'userId') ?: $actorId,
            'name' => $this->stringValue($data, 'name'),
            'content' => $this->stringValue($data, 'content'),
            'imageUris' => $this->uniqueStrings($data['imageUris'] ?? []),
            'contentBlocks' => $this->listValue($data['contentBlocks'] ?? []),
            'styleRanges' => $this->listValue($data['styleRanges'] ?? []),
            'alignment' => $this->stringValue($data, 'alignment') ?: 'Start',
            'alignmentRanges' => $this->listValue($data['alignmentRanges'] ?? []),
            'description' => $this->stringValue($data, 'description'),
            'date' => $this->stringValue($data, 'date'),
            'time' => $this->stringValue($data, 'time'),
        ];

        $reference->set($note);

        return $note;
    }

    public function findOrFail(string $noteId): array
    {
        $snapshot = $this->document($noteId)->snapshot();

        if (! $snapshot->exists()) {
            throw new RuntimeException('Note not found.');
        }

        return $snapshot->data();
    }

    public function forUser(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        $items = [];
        foreach ($this->collection()->where('userId', '=', $userId)->documents() as $document) {
            if ($document->exists()) {
                $items[] = $document->data();
            }
        }

        return $items;
    }

    public function delete(string $noteId): void
    {
        $this->findOrFail($noteId);
        $this->document($noteId)->delete();
    }

    private function collection(): CollectionReference
    {
        return $this->firebase
            ->firestore()
            ->collection($this->firebase->collectionName('notes'));
    }

    private function document(string $noteId): DocumentReference
    {
        return $this->collection()->document($noteId);
    }

    private function stringValue(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
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
