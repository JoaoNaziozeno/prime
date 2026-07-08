<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_of_service_id')->index();
            $table->uuid('assigned_to')->nullable()->index(); // Driver/user assigned
            $table->uuid('created_by')->index();

            // Service/Item details
            $table->string('description');
            $table->longText('notes')->nullable();
            $table->integer('sequence')->default(0); // Order of execution

            // Quantity and pricing
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('actual_cost', 12, 2)->nullable();

            // Status workflow
            $table->enum('status', [
                'pending',       // Aguardando execução
                'in_progress',   // Em execução
                'completed',     // Concluído
                'cancelled',     // Cancelado
                'blocked',       // Bloqueado (depende de outro item)
            ])->default('pending')->index();

            // Timing
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('hours_spent', 6, 2)->nullable();

            // Additional data
            $table->json('metadata')->nullable();

            // Audit
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
