<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();
            $table->uuid('tenant_id');

            $table->string('trigger_event');
            $table->string('subject');
            $table->longText('email_body_html');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('is_active');
            $table->unique(['tenant_id', 'trigger_event']);
            $table->index(['trigger_event', 'is_active']);
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->uuid('setting_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');

            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('push_notifications_enabled')->default(true);
            $table->boolean('sms_notifications_enabled')->default(false);
            $table->boolean('in_app_notifications_enabled')->default(true);

            $table->json('notification_preferences')->nullable();

            $table->timestamps();

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
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('email_templates');
    }
};
