<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();
            $table->uuid('order_of_service_id')->nullable()->index();
            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->dateTime('start_time')->index();
            $table->dateTime('end_time')->index();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('scheduled')->index();
            $table->uuid('assigned_to')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('set null');
            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
