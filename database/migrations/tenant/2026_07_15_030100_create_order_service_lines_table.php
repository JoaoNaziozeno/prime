<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_service_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_of_service_id')->index();
            $table->uuid('service_id')->index();
            $table->uuid('assigned_to')->nullable()->index(); // mechanic/driver/user executing
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('hours_spent', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_service_lines');
    }
};
