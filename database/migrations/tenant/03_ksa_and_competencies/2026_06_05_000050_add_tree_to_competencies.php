<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the self-referencing hierarchy + soft-delete support the original
 * competencies schema lacked, bringing it in line with the Category "gold
 * standard". The parent link is intentionally a plain indexed column (no DB
 * FK) — adding a self-referencing FK via ALTER is fragile on SQLite, and tree
 * integrity (parent exists, no cycles) is enforced in CompetencyTreeService.
 * Guarded with hasColumn so it is safe to re-run / apply to a drifted schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('competencies', 'parent_competency_id')) {
            Schema::table('competencies', function (Blueprint $table): void {
                $table->uuid('parent_competency_id')->nullable()->after('tenant_id');
                $table->unsignedInteger('hierarchy_level')->default(0)->after('parent_competency_id');
                $table->index('parent_competency_id');
            });
        }

        if (! Schema::hasColumn('competencies', 'deleted_at')) {
            Schema::table('competencies', function (Blueprint $table): void {
                $table->softDeletes();
                $table->index('deleted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('competencies', 'deleted_at')) {
            Schema::table('competencies', function (Blueprint $table): void {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('competencies', 'parent_competency_id')) {
            Schema::table('competencies', function (Blueprint $table): void {
                $table->dropIndex(['parent_competency_id']);
                $table->dropColumn(['parent_competency_id', 'hierarchy_level']);
            });
        }
    }
};
