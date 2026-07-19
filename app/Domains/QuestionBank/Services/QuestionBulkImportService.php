<?php

declare(strict_types=1);

namespace App\Domains\QuestionBank\Services;

use App\Domains\QuestionBank\Contracts\QuestionManagementService;
use App\Domains\QuestionBank\Models\Category;
use App\Domains\QuestionBank\Models\QuestionImportLog;
use League\Csv\Reader;
use RuntimeException;

class QuestionBulkImportService
{
    public function __construct(
        private readonly QuestionManagementService $questions,
    ) {
    }

    /**
     * @return array{log: QuestionImportLog, errors: array<int, array{row: int, message: string}>}
     */
    public function importFromCsv(string $filePath, string $importedByUserId): array
    {
        $startedAt = now();
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);

        $total = 0;
        $success = 0;
        $errors = [];

        foreach ($csv->getRecords() as $rowIndex => $record) {
            $total++;

            try {
                $categoryId = Category::query()
                    ->where('category_code', trim((string) $record['category_code']))
                    ->value('category_id');

                if ($categoryId === null) {
                    throw new RuntimeException("Category code '{$record['category_code']}' not found.");
                }

                $choices = json_decode((string) ($record['choices_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
                $answer = json_decode((string) ($record['answer_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);

                $this->questions->createQuestion(
                    categoryId: (string) $categoryId,
                    createdByUserId: $importedByUserId,
                    title: (string) $record['question_title'],
                    type: (string) $record['question_type'],
                    questionText: (string) $record['question_text'],
                    stem: $record['stem'] !== '' ? (string) $record['stem'] : null,
                    bloomLevel: (int) $record['bloom_level'],
                    difficultyLevel: (int) $record['difficulty_level'],
                    choices: $choices,
                    answer: $answer,
                );

                $success++;
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowIndex + 2, 'message' => $e->getMessage()];
            }
        }

        $log = QuestionImportLog::create([
            'imported_by_user_id' => $importedByUserId,
            'import_source' => 'csv_upload',
            'total_questions_imported' => $total,
            'successful_imports' => $success,
            'failed_imports' => count($errors),
            'error_details' => $errors,
            'import_started_at' => $startedAt,
            'import_completed_at' => now(),
        ]);

        return ['log' => $log, 'errors' => $errors];
    }
}