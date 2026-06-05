<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Priority #4 — drop the dead `options_json` column. Options are stored in the
 * normalized `question_options` table (the single source of truth); this column
 * was never written or read by the service.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('question_versions', 'options_json')) {
            Schema::table('question_versions', function (Blueprint $table): void {
                $table->dropColumn('options_json');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('question_versions', 'options_json')) {
            Schema::table('question_versions', function (Blueprint $table): void {
                $table->json('options_json')->nullable();
            });
        }
    }
};
