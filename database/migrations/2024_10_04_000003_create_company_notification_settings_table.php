<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('notifications')->create('company_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_subdomain', 100);
            $table->foreignId('notification_group_id')->constrained('notification_groups');
            $table->string('template_name', 100);
            $table->boolean('is_active')->default(true);
            $table->json('additional_settings')->nullable();
            $table->timestamps();
            
            $table->unique(['company_subdomain', 'notification_group_id'], 'unique_company_group');
            $table->index('company_subdomain');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('notifications')->dropIfExists('company_notification_settings');
    }
};