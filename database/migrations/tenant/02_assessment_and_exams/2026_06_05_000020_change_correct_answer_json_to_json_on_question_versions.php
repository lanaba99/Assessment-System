<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Priority #3 — `correct_answer_json` was a `text` column and uncast, while it
 * holds structured answer keys ({value:bool} for true/false,
 * {accepted:[...],match:...} for short answer). Promote it to a real `json`
 * column so it is consistent with explanation_text / evaluator_instructions
 * and can be cast to array on the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_versions', function (Blueprint $table): void {
            $table->json('correct_answer_json')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('question_versions', function (Blueprint $table): void {
            $table->text('correct_answer_json')->nullable()->change();
        });
    }
};
