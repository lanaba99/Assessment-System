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
        $certificateCode = 'CERT-' . strtoupper(Str::random(10));

        $verificationUrl = route('api.v1.certificates.verify', ['token' => $certificateCode]);
        $qrDataUri = (new QRCode())->render($verificationUrl);

        $pdf = Pdf::loadView('certificates.certificate', [
            'candidateName' => $result->candidate->first_name . ' ' . $result->candidate->last_name,
            'examName' => $result->exam->exam_name ?? 'Assessment',
            'finalScore' => $grade->final_score,
            'gradeLetter' => $grade->grade_letter,
            'certificateNumber' => $certificateCode,
            'issuedAt' => now()->format('Y-m-d'),
            'qrDataUri' => $qrDataUri,
        ]);

        Storage::disk('local')->put("certificates/{$certificateCode}.pdf", $pdf->output());

        return Certificate::create([
            'candidate_user_id' => $result->candidate_user_id,
            'assessment_result_id' => $result->result_id,
            'exam_id' => $result->exam_id,
            'certificate_code' => $certificateCode,
            'qr_code_data' => $verificationUrl,
            'issued_at' => now(),
            'verification_status' => 'valid',
        ]);
    }
}