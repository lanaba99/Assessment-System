<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_tags', function (Blueprint $table) {
            $table->uuid('tag_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('question_id');

            $table->string('tag_name');

            $table->timestamp('created_at')->nullable();

            $table->foreign('question_id')
                ->references('question_id')
                ->on('questions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['question_id', 'tag_name']);
            $table->index('tenant_id');
            $table->index('tag_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_tags');
    }
};
