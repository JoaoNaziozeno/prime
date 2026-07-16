<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id')->index();
            $table->uuid('payment_method_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('gateway');
            $table->string('gateway_transaction_id')->nullable()->index();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending')->index();
            $table->dateTime('paid_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
