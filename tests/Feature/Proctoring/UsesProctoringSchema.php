<?php

declare(strict_types=1);

namespace Tests\Feature\Proctoring;

use App\Domains\Proctoring\Models\ProctorLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\ExamSession\UsesExamSessionSchema;

/**
 * Extends the ExamSession schema with the proctoring_events table required
 * by Proctoring-domain feature tests. Mirrors
 * database/migrations/tenant/04_proctoring_and_security/..._create_proctoring_events_table.php —
 * keep in lockstep if that migration changes.
 */
trait UsesProctoringSchema
{
    use UsesExamSessionSchema;

    protected function bootProctoringSchema(): void
    {
        $this->bootExamSessionSchema();
        $this->migrateProctoringTables();
    }

    private function migrateProctoringTables(): void
    {
        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            Schema::connection($connection)->disableForeignKeyConstraints();
            Schema::connection($connection)->dropIfExists('proctoring_events');
            Schema::connection($connection)->enableForeignKeyConstraints();
        }

        if (! Schema::hasTable('proctoring_events')) {
            Schema::create('proctoring_events', function (Blueprint $table): void {
                $table->uuid('event_id')->primary();
                $table->uuid('session_id');
                $table->uuid('candidate_user_id');
                $table->uuid('tenant_id');
                $table->uuid('reviewing_proctor_id')->nullable();

                $table->dateTime('event_timestamp');
                $table->string('event_type');
                $table->string('event_category')->nullable();

                $table->json('event_payload')->nullable();
                $table->json('detection_parameters')->nullable();

                $table->string('severity_level')->default('info');
                $table->decimal('detection_confidence_score', 5, 4)->nullable();

                $table->string('screenshot_url')->nullable();
                $table->string('video_segment_url')->nullable();

                $table->boolean('requires_investigation')->default(false);
                $table->boolean('is_escalated')->default(false);

                $table->string('investigation_status')->default('open');
                $table->json('investigation_notes')->nullable();

                $table->timestamp('created_at')->nullable();

                $table->foreign('session_id')
                    ->references('session_id')->on('exam_sessions')
                    ->onUpdate('cascade')->onDelete('cascade');
                $table->foreign('candidate_user_id')
                    ->references('id')->on('users')
                    ->onUpdate('cascade')->onDelete('cascade');
                $table->foreign('reviewing_proctor_id')
                    ->references('id')->on('users')
                    ->onUpdate('cascade')->onDelete('set null');

                $table->index('tenant_id');
            });
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createProctorLog(
        string $tenantId,
        string $sessionId,
        string $candidateUserId,
        array $overrides = [],
    ): ProctorLog {
        return ProctorLog::query()->forceCreate(array_merge([
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'candidate_user_id' => $candidateUserId,
            'tenant_id' => $tenantId,
            'event_timestamp' => now(),
            'event_type' => 'tab_switch',
            'event_category' => 'browser_activity',
            'severity_level' => 'warning',
            'requires_investigation' => false,
            'is_escalated' => false,
            'investigation_status' => 'open',
            'created_at' => now(),
        ], $overrides));
    }
}