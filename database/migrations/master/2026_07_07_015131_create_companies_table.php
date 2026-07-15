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
        Schema::create('companies', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Company data
            $table->string('name')->index();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('cnpj')->unique();
            $table->string('website')->nullable();

            // Address
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('BR');
            $table->string('zip_code')->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();

            // Ownership
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            // Timestamps & Soft Delete
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullifyOnDelete();

            // Indexes
            $table->index('cnpj');
            $table->index(['created_at', 'status']);
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('user');
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
