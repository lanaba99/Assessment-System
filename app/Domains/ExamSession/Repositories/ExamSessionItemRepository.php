<?php

declare(strict_types=1);

namespace App\Domains\ExamSession\Repositories;

use App\Domains\ExamSession\Exceptions\StaleVersionLockException;
use App\Domains\ExamSession\Models\ExamSessionItem;
use Illuminate\Support\Collection;

class ExamSessionItemRepository
{
    public function __construct(
        private readonly ExamSessionItem $item,
    ) {
    }

    public function findById(string $sessionItemId): ?ExamSessionItem
    {
        return $this->item->newQuery()->find($sessionItemId);
    }

    /**
     * Pessimistic lock — must be called inside an active DB transaction.
     */
    public function findByIdForUpdate(string $sessionItemId): ?ExamSessionItem
    {
        return $this->item
            ->newQuery()
            ->where('session_item_id', $sessionItemId)
            ->lockForUpdate()
            ->first();
    }

    public function findBySession(string $sessionId): Collection
    {
        return $this->item
            ->newQuery()
            ->where('session_id', $sessionId)
            ->orderBy('sequence_number')
            ->get();
    }

    public function findBySessionAndId(string $sessionId, string $sessionItemId): ?ExamSessionItem
    {
        return $this->item
            ->newQuery()
            ->where('session_id', $sessionId)
            ->where('session_item_id', $sessionItemId)
            ->first();
    }

    /**
     * Returns the lowest-sequence pending item for the session, or null when
     * all items have been answered (or the session has no items yet).
     * Used to populate the ExamSessionView's current-item fields.
     */
    public function findNextPending(string $sessionId): ?ExamSessionItem
    {
        return $this->item
            ->newQuery()
            ->where('session_id', $sessionId)
            ->where('item_state', 'pending')
            ->orderBy('sequence_number')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ExamSessionItem
    {
        $attributes['version_lock'] = $attributes['version_lock'] ?? 0;

        return $this->item->newQuery()->forceCreate($attributes);
    }

    /**
     * Optimistic update — compares the in-memory `version_lock` against the
     * persisted row and atomically increments it. Throws if another process
     * has modified the row since this instance was loaded.
     */
    public function update(ExamSessionItem $item, array $attributes): ExamSessionItem
    {
        $expected = (int) $item->version_lock;
        $next = $expected + 1;

        $payload = array_merge($attributes, ['version_lock' => $next]);

        $affected = $this->item
            ->newQuery()
            ->where('session_item_id', $item->session_item_id)
            ->where('version_lock', $expected)
            ->update($payload);

        if ($affected === 0) {
            throw StaleVersionLockException::forSessionItem((string) $item->session_item_id, $expected);
        }

        $item->forceFill($payload);
        $item->syncOriginal();

        return $item;
    }
}
