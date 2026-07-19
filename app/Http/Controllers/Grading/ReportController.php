<?php

declare(strict_types=1);

namespace App\Http\Controllers\Grading;

use App\Domains\Grading\Models\Grade;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function exportResults(Request $request, string $examId): StreamedResponse
    {
        $grades = Grade::query()
            ->where('exam_id', $examId)
            ->where('is_final_grade', true)
            ->with('candidate')
            ->get();

        $filename = "exam-{$examId}-results.csv";

        return response()->streamDownload(function () use ($grades) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Candidate Name', 'Final Score', 'Grade', 'Passing', 'Graded At']);

            foreach ($grades as $g) {
                fputcsv($handle, [
                    trim(($g->candidate->first_name ?? '') . ' ' . ($g->candidate->last_name ?? '')),
                    $g->final_score,
                    $g->grade_letter,
                    $g->is_passing_grade ? 'Yes' : 'No',
                    optional($g->graded_at)->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}