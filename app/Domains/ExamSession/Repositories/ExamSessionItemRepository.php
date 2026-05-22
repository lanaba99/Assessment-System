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

    public function create(array $attributes): ExamSessionItem
    {
        $attributes['version_lock'] = $attributes['version_lock'] ?? 0;

        return $this->item->newQuery()->create($attributes);
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
