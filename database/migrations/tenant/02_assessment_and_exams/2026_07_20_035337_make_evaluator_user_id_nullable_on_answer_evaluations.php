<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE answer_evaluations DROP FOREIGN KEY answer_evaluations_evaluator_user_id_foreign');

        Schema::table('answer_evaluations', function (Blueprint $table) {
            $table->uuid('evaluator_user_id')->nullable()->change();
        });

        Schema::table('answer_evaluations', function (Blueprint $table) {
            $table->foreign('evaluator_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE answer_evaluations DROP FOREIGN KEY answer_evaluations_evaluator_user_id_foreign');

        Schema::table('answer_evaluations', function (Blueprint $table) {
            $table->uuid('evaluator_user_id')->nullable(false)->change();
        });

        Schema::table('answer_evaluations', function (Blueprint $table) {
            $table->foreign('evaluator_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }
};