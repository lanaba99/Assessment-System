<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_conditions', function (Blueprint $table) {
            $table->uuid('condition_id')->primary();
            $table->uuid('rule_id');

            $table->string('condition_type');
            $table->json('condition_definition');

            $table->string('comparison_operator')->nullable();
            $table->string('logical_operator')->nullable();

            $table->unsignedTinyInteger('nesting_level')->default(0);

            $table->json('condition_metadata')->nullable();

            $table->foreign('rule_id')
                ->references('rule_id')
                ->on('rules')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('condition_type');
            $table->index('comparison_operator');
            $table->index('logical_operator');
            $table->index('nesting_level');
            $table->index(['rule_id', 'nesting_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_conditions');
    }
};
