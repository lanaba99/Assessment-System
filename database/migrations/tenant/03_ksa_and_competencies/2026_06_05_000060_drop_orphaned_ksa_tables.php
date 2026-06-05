<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the orphaned KSA scaffolding tables left behind after the Competency
 * module was rebuilt as a clean tree. Their PHP models/services were removed
 * and no code reads or writes them any longer (the seeder's competency_levels
 * block was also removed).
 *
 * Tables are listed children-first (skill_gap_mappings FKs skill_gaps) and FK
 * checks are disabled, so the drop is safe and order-independent. Guarded by
 * dropIfExists, making it idempotent across drifted tenant databases.
 *
 * down() is intentionally a no-op: these tables are deprecated, not restored.
 * Their original create migrations remain in history for the audit trail.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'skill_gap_mappings',
        'skill_gaps',
        'competency_levels',
        'ksa_matrix_templates',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Deprecated tables — intentionally not recreated.
    }
};
