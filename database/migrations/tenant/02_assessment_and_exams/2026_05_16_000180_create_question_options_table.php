<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->uuid('option_id')->primary();
            $table->uuid('version_id');

            $table->unsignedInteger('option_sequence');
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);

            $table->json('option_metadata')->nullable();

            $table->foreign('version_id')
                ->references('version_id')
                ->on('question_versions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['version_id', 'option_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
