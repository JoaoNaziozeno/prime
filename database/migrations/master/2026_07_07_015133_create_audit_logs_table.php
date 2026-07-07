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
        Schema::create('audit_logs', function (Blueprint $table) {
            // Primary key
            $table->id();

            // User info
            $table->foreignId('user_id')->nullable()->constrained('users')->nullifyOnDelete();
            $table->string('user_email')->nullable();
            $table->string('user_name')->nullable();

            // Model info
            $table->string('model_type'); // Full namespace
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('model_name')->nullable();

            // Action
            $table->enum('action', ['created', 'updated', 'deleted', 'restored', 'custom'])->index();
            $table->text('description')->nullable();

            // Changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable(); // Array of changed field names

            // Request info
            $table->string('ip_address')->nullable()->index();
            $table->string('user_agent')->nullable();
            $table->string('method'); // GET, POST, PUT, DELETE
            $table->string('endpoint')->nullable();
            $table->integer('status_code')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->string('source')->default('web'); // web, api, cli, etc

            // Timestamp (use created_at as audit time)
            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at')->nullable();

            // Indexes for common queries
            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
