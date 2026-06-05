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
            $table->timestamp('last_heartbeat_at')->nullable()->after('session_ended_at');
            $table->json('heartbeat_metadata')->nullable()->after('last_heartbeat_at');

            $table->index('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropIndex(['last_heartbeat_at']);
            $table->dropColumn(['last_heartbeat_at', 'heartbeat_metadata']);
        });
    }
};
