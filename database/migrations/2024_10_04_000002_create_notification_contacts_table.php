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
        Schema::connection('notifications')->create('notification_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('notification_groups')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('phone_number', 20);
            $table->string('role', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['group_id', 'is_active']);
            $table->index('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('notifications')->dropIfExists('notification_contacts');
    }
};