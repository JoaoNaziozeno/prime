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
        // 0. Add odometer to vehicles
        Schema::table('vehicles', function (Blueprint $table) {
            $table->integer('odometer')->default(0)->after('status');
        });

        // 1. preventive_rules
        Schema::create('preventive_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('interval_kms');
            $table->integer('interval_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
        });

        // 2. maintenance_logs
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->uuid('preventive_rule_id')->nullable()->index();
            $table->uuid('order_of_service_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['preventive', 'corrective', 'predictive'])->index();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled')->index();
            $table->date('scheduled_date');
            $table->date('completed_date')->nullable();
            $table->integer('odometer')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->uuid('created_by')->index();
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->foreign('preventive_rule_id')->references('id')->on('preventive_rules')->onDelete('set null');
            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('set null');
        });

        // 3. vehicle_preventive_rule_status
        Schema::create('vehicle_preventive_rule_status', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->uuid('preventive_rule_id')->index();
            $table->integer('last_performed_kms')->default(0);
            $table->date('last_performed_date')->nullable();
            $table->integer('next_due_kms');
            $table->date('next_due_date')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->foreign('preventive_rule_id')->references('id')->on('preventive_rules')->onDelete('cascade');
            $table->unique(['vehicle_id', 'preventive_rule_id'], 'vehicle_rule_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_preventive_rule_status');
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('preventive_rules');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('odometer');
        });
    }
};
