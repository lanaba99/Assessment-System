<?php

declare(strict_types=1);

namespace App\Http\Resources\ExamEngine;

use App\Domains\ExamEngine\Models\ExamSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamSection
 */
class ExamSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->section_id,
            'exam_id' => (string) $this->exam_id,
            'section_name' => (string) $this->section_name,
            'section_code' => $this->section_code,
            'section_sequence' => (int) $this->section_sequence,
            'questions_in_section' => (int) $this->questions_in_section,
            'time_limit_minutes' => $this->time_limit_minutes !== null ? (int) $this->time_limit_minutes : null,
            'branching_logic' => $this->branching_logic,
            'section_metadata' => $this->section_metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
