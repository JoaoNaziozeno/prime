<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders_of_service', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->index();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable();

            // Status workflow
            $table->enum('status', [
                'draft',           // Rascunho
                'pending_approval', // Aguardando aprovação
                'approved',        // Aprovada
                'in_progress',     // Em andamento
                'completed',       // Concluída
                'cancelled',       // Cancelada
                'on_hold',         // Parada/Suspensa
            ])->default('draft')->index();

            // Priority
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->index();

            // Service details
            $table->text('description');
            $table->longText('internal_notes')->nullable();

            // Dates
            $table->dateTime('start_date')->nullable();
            $table->dateTime('expected_end_date')->nullable();
            $table->dateTime('actual_end_date')->nullable();

            // Financial tracking
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();

            // Additional tracking
            $table->string('reference_number')->unique(); // Número OS para referência
            $table->json('metadata')->nullable();

            // Audit
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders_of_service');
    }
};
