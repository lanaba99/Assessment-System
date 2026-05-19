<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_settings', function (Blueprint $table) {
            $table->uuid('config_id')->primary();
            $table->uuid('tenant_id');

            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();

            $table->string('custom_logo_url')->nullable();
            $table->string('custom_domain_url')->nullable();

            $table->longText('custom_css')->nullable();

            $table->string('email_sender_name')->nullable();
            $table->string('email_sender_address')->nullable();

            $table->json('brand_metadata')->nullable();

            $table->timestamps();

            $table->unique('tenant_id');
            $table->index('custom_domain_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_settings');
    }
};
