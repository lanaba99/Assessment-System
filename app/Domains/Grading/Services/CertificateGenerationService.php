<?php

declare(strict_types=1);

namespace App\Domains\Grading\Services;

use App\Domains\Grading\Models\AssessmentResult;
use App\Domains\Grading\Models\Certificate;
use App\Domains\Grading\Models\Grade;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateGenerationService
{
    public function generate(AssessmentResult $result, Grade $grade): Certificate
    {
        $certificateNumber = 'CERT-' . strtoupper(Str::random(10));
        $token = (string) Str::uuid();

        $qrDataUri = (new QRCode())->render(
            route('api.v1.certificates.verify', ['token' => $token])
        );

        $pdf = Pdf::loadView('certificates.certificate', [
            'candidateName' => $result->candidate->first_name . ' ' . $result->candidate->last_name,
            'examName' => $result->exam->exam_title ?? 'Assessment',
            'finalScore' => $grade->final_score,
            'gradeLetter' => $grade->grade_letter,
            'certificateNumber' => $certificateNumber,
            'issuedAt' => now()->format('Y-m-d'),
            'qrDataUri' => $qrDataUri,
        ]);

        $path = "certificates/{$certificateNumber}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return Certificate::create([
            'result_id' => $result->result_id,
            'session_id' => $result->session_id,
            'candidate_user_id' => $result->candidate_user_id,
            'certificate_number' => $certificateNumber,
            'verification_token' => $token,
            'pdf_path' => $path,
            'issued_at' => now(),
        ]);
    }
}