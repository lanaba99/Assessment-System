<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropColumn('version_lock');
        });

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('version_lock')->default(0)->after('last_heartbeat_at');
        });

        Schema::table('exam_session_items', function (Blueprint $table): void {
            $table->dropColumn('version_lock');
        });

        Schema::table('exam_session_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('version_lock')->default(0)->after('is_flagged');
        });
    }

    public function down(): void
    {
        Schema::table('exam_session_items', function (Blueprint $table): void {
            $table->dropColumn('version_lock');
        });

        Schema::table('exam_session_items', function (Blueprint $table): void {
            $table->timestamp('version_lock')->nullable()->after('is_flagged');
        });

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropColumn('version_lock');
        });

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->timestamp('version_lock')->nullable()->after('last_heartbeat_at');
        });
    }
};
