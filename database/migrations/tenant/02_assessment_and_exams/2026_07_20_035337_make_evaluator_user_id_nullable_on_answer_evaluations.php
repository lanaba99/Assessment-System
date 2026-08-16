<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answer_evaluations', function (Blueprint $table): void {
            $table->dropForeign(['evaluator_user_id']);
        });

        Schema::table('answer_evaluations', function (Blueprint $table): void {
            $table->uuid('evaluator_user_id')->nullable()->change();
        });

        Schema::table('answer_evaluations', function (Blueprint $table): void {
            $table->foreign('evaluator_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('answer_evaluations', function (Blueprint $table): void {
            $table->dropForeign(['evaluator_user_id']);
        });

        Schema::table('answer_evaluations', function (Blueprint $table): void {
            $table->uuid('evaluator_user_id')->nullable(false)->change();
        });

        Schema::table('answer_evaluations', function (Blueprint $table): void {
            $table->foreign('evaluator_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }
};