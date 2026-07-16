<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_of_service_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('branch_id')->index();
            $table->uuid('payment_term_id')->nullable()->index();
            $table->uuid('payment_method_id')->nullable()->index();
            $table->string('invoice_number')->unique();
            $table->enum('status', ['draft', 'sent', 'partially_paid', 'paid', 'cancelled'])->default('draft')->index();
            $table->dateTime('issue_date');
            $table->dateTime('due_date');
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('payment_term_id')->references('id')->on('payment_terms')->onDelete('set null');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
