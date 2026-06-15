<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This updates legacy permission names to the current canonical naming convention
     * to match the enforcement logic in the domain Policies.
     */
    public function up(): void
    {
        $mapping = [
            'evaluations.score' => 'grading.evaluate',
            'sessions.proctor'  => 'exam_sessions.start',
            'exams.take'        => 'exams.view',
        ];

        foreach ($mapping as $oldName => $newName) {
            DB::table('permissions')
                ->where('permission_name', $oldName)
                ->update(['permission_name' => $newName]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $mapping = [
            'grading.evaluate'    => 'evaluations.score',
            'exam_sessions.start' => 'sessions.proctor',
            'exams.view'          => 'exams.take',
        ];

        foreach ($mapping as $newName => $oldName) {
            DB::table('permissions')
                ->where('permission_name', $newName)
                ->update(['permission_name' => $oldName]);
        }
    }
};