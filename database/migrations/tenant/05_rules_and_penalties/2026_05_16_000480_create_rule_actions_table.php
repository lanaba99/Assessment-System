<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_actions', function (Blueprint $table) {
            $table->uuid('action_id')->primary();
            $table->uuid('rule_id');

            $table->string('action_type');
            $table->json('action_parameters')->nullable();

            $table->decimal('action_value', 12, 4)->nullable();

            $table->integer('execution_sequence')->default(0);

            $table->json('action_metadata')->nullable();

            $table->foreign('rule_id')
                ->references('rule_id')
                ->on('rules')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('action_type');
            $table->index('execution_sequence');
            $table->index(['rule_id', 'execution_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_actions');
    }
};
