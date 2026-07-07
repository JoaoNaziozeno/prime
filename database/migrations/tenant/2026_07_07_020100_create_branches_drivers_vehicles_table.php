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
        // Branches/Filiais
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('code')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Address
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('country', 2)->default('BR');
            $table->string('zip_code')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Drivers/Motoristas
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cpf')->nullable()->unique();
            $table->string('cnh')->nullable()->unique();

            // CNH details
            $table->string('cnh_category')->nullable();
            $table->date('cnh_expiration')->nullable();

            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->index();
            $table->timestamp('hired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Vehicles/Veículos
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate')->unique()->index();
            $table->string('model')->index();
            $table->string('brand')->index();
            $table->year('year');

            // Vehicle details
            $table->enum('type', ['truck', 'van', 'car', 'motorcycle', 'trailer'])->index();
            $table->string('vin')->nullable()->unique();
            $table->string('color')->nullable();
            $table->decimal('capacity_tons', 8, 2)->nullable();

            // Registration
            $table->string('renavam')->nullable();
            $table->date('license_expiration')->nullable();

            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active')->index();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('branches');
    }
};
