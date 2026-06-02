<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Repositories;

use App\Domains\QuestionBank\Models\QuestionOption;
use App\Domains\QuestionBank\Models\QuestionPsychometrics;
use App\Domains\QuestionBank\Models\QuestionVersion;
use Illuminate\Support\Collection;

class QuestionVersionRepository
{
    public function __construct(
        private readonly QuestionVersion $versionModel,
        private readonly QuestionOption $optionModel,
        private readonly QuestionPsychometrics $psychometricsModel,
    ) {
    }

    public function create(array $attributes): QuestionVersion
    {
        return $this->versionModel->newQuery()->create($attributes);
    }

    public function update(QuestionVersion $version, array $attributes): QuestionVersion
    {
        $version->fill($attributes);
        $version->save();

        return $version;
    }

    /**
     * @param  array<int, array{option_text: string, is_correct: bool, option_sequence?: int, option_metadata?: array|null}>  $choices
     */
    public function replaceOptions(QuestionVersion $version, array $choices): Collection
    {
        $this->optionModel
            ->newQuery()
            ->where('version_id', $version->version_id)
            ->delete();

        $created = collect();

        foreach ($choices as $index => $choice) {
            $created->push($this->optionModel->newQuery()->create([
                'version_id' => $version->version_id,
                'option_sequence' => $choice['option_sequence'] ?? ($index + 1),
                'option_text' => $choice['option_text'],
                'is_correct' => (bool) ($choice['is_correct'] ?? false),
                'option_metadata' => $choice['option_metadata'] ?? null,
            ]));
        }

        return $created;
    }

    public function createPsychometrics(string $tenantId, string $versionId, array $attributes): QuestionPsychometrics
    {
        return $this->psychometricsModel->newQuery()->create(array_merge([
            'tenant_id' => $tenantId,
            'question_version_id' => $versionId,
        ], $attributes));
    }

    public function updatePsychometrics(QuestionPsychometrics $psychometrics, array $attributes): QuestionPsychometrics
    {
        $psychometrics->fill($attributes);
        $psychometrics->save();

        return $psychometrics;
    }

    public function findPsychometricsByVersionId(string $tenantId, string $versionId): ?QuestionPsychometrics
    {
        return $this->psychometricsModel
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('question_version_id', $versionId)
            ->first();
    }
}
