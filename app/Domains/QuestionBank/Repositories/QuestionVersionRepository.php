<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Repositories;

use App\Domains\QuestionBank\Models\QuestionOption;
use App\Domains\QuestionBank\Models\QuestionPsychometrics;
use App\Domains\QuestionBank\Models\QuestionVersion;
use Illuminate\Support\Collection;

/**
 * Versions are append-only. There is deliberately no method that mutates the
 * content (text/stem/options) of an existing version — content edits create a
 * brand-new version row via the service. `update()` exists only for
 * version-level metadata transitions (e.g. approval status).
 */
class QuestionVersionRepository
{
    public function __construct(
        private readonly QuestionVersion $versionModel,
        private readonly QuestionOption $optionModel,
        private readonly QuestionPsychometrics $psychometricsModel,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): QuestionVersion
    {
        // Trusted boundary — forceCreate writes server-controlled columns
        // (question_id, ver_num, content_hash, approval_status, …) that are
        // intentionally excluded from $fillable.
        return $this->versionModel->newQuery()->forceCreate($attributes);
    }

    /**
     * Metadata-only updates (approval transitions, usage counters). NEVER call
     * this to change question content — spawn a new version instead.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(QuestionVersion $version, array $attributes): QuestionVersion
    {
        $version->forceFill($attributes)->save();

        return $version;
    }

    /**
     * The next sequential version number for a question. Counts trashed rows
     * (`withTrashed`) so numbering stays strictly monotonic and never collides
     * with the unique(question_id, ver_num) index after a soft delete.
     */
    public function nextVersionNumber(string $questionId): int
    {
        $max = (int) $this->versionModel
            ->newQuery()
            ->withTrashed()
            ->where('question_id', $questionId)
            ->max('ver_num');

        return $max + 1;
    }

    /**
     * Insert the option set for a (fresh) version. Versions are immutable, so
     * we only ever create options — never delete-and-replace on a live row.
     *
     * @param  array<int, array{option_text: string, is_correct: bool, option_sequence?: int, option_metadata?: array<string, mixed>|null}>  $choices
     * @return Collection<int, QuestionOption>
     */
    public function createOptions(QuestionVersion $version, array $choices): Collection
    {
        $created = collect();

        foreach (array_values($choices) as $index => $choice) {
            $created->push($this->optionModel->newQuery()->forceCreate([
                'version_id' => $version->version_id,
                'option_sequence' => $choice['option_sequence'] ?? ($index + 1),
                'option_text' => $choice['option_text'],
                'is_correct' => (bool) ($choice['is_correct'] ?? false),
                'option_metadata' => $choice['option_metadata'] ?? null,
            ]));
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPsychometrics(string $versionId, array $attributes): QuestionPsychometrics
    {
        return $this->psychometricsModel->newQuery()->forceCreate(array_merge([
            'question_version_id' => $versionId,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updatePsychometrics(QuestionPsychometrics $psychometrics, array $attributes): QuestionPsychometrics
    {
        $psychometrics->forceFill($attributes)->save();

        return $psychometrics;
    }

    public function findPsychometricsByVersionId(string $versionId): ?QuestionPsychometrics
    {
        return $this->psychometricsModel
            ->newQuery()
            ->where('question_version_id', $versionId)
            ->first();
    }
}
