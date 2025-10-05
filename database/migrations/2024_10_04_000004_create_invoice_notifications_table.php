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
        Schema::connection('notifications')->create('invoice_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('company_subdomain', 100);
            $table->bigInteger('invoice_id');
            $table->string('invoice_file_name', 255);
            $table->string('error_code', 10);
            $table->text('error_description')->nullable();
            $table->timestamp('invoice_date');
            $table->timestamp('notified_at');
            $table->string('template_used', 100);
            $table->foreignId('notification_group_id')->constrained('notification_groups');
            $table->json('delivery_results');
            $table->enum('status', ['sent', 'failed', 'partial'])->default('sent');
            $table->timestamps();
            
            // Prevenir duplicados
            $table->unique(['company_subdomain', 'invoice_id', 'notification_group_id'], 'unique_company_invoice_group');
            $table->index(['company_subdomain', 'notified_at']);
            $table->index(['company_subdomain', 'status']);
            $table->index('notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('notifications')->dropIfExists('invoice_notifications');
    }
};