<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_responses', function (Blueprint $table): void {
            $table->dropColumn('version_lock');
        });
    }

    public function down(): void
    {
        Schema::table('question_responses', function (Blueprint $table): void {
            $table->timestamp('version_lock')->nullable();
        });
    }
};
