<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->uuid('preference_id')->primary();
            $table->uuid('user_id');
            $table->uuid('tenant_id');

            $table->string('theme_preference')->default('light');
            $table->string('language_preference')->default('en');
            $table->string('date_format')->default('Y-m-d');
            $table->string('time_format')->default('H:i');

            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('push_notifications_enabled')->default(true);
            $table->boolean('sms_notifications_enabled')->default(false);

            $table->json('additional_preferences')->nullable();

            $table->timestamp('updated_at')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['user_id', 'tenant_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
