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
        Schema::create('plans', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Plan data
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['monthly', 'yearly', 'custom'])->default('monthly');
            $table->decimal('price', 10, 2);
            $table->integer('billing_cycle_days')->default(30);

            // Features
            $table->integer('max_users')->default(5);
            $table->integer('max_branches')->default(1);
            $table->integer('max_storage_gb')->default(10);
            $table->boolean('has_api_access')->default(false);
            $table->boolean('has_support')->default(true);
            $table->text('features')->nullable(); // JSON

            // Status
            $table->boolean('is_active')->default(true)->index();
            $table->integer('trial_days')->default(0);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('is_active');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
