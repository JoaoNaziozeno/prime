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
        Schema::create('tenants', function (Blueprint $table) {
            // Primary key (UUID recommended)
            $table->uuid('id')->primary();

            // Relationships
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // Tenant config
            $table->string('slug')->unique();
            $table->string('database_name')->unique();
            $table->string('hostname')->nullable()->unique();

            // Database connection
            $table->string('db_host')->default('127.0.0.1');
            $table->integer('db_port')->default(3306);
            $table->string('db_username')->nullable();
            $table->string('db_password')->nullable();
            $table->enum('db_driver', ['mysql', 'pgsql', 'sqlite'])->default('mysql');

            // Status
            $table->enum('status', ['setup', 'active', 'paused', 'deleted'])->default('setup')->index();
            $table->timestamp('activated_at')->nullable();

            // Config
            $table->json('settings')->nullable();
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullifyOnDelete();

            // Indexes
            $table->index('company_id');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
