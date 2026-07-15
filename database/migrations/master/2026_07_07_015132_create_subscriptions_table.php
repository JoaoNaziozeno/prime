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
        Schema::create('subscriptions', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Relationships
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();

            // Subscription status
            $table->enum('status', ['active', 'paused', 'cancelled', 'expired'])->default('active')->index();

            // Billing
            $table->timestamp('started_at');
            $table->timestamp('renews_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->decimal('current_amount', 10, 2);

            // Payment info
            $table->string('payment_method')->nullable(); // credit_card, boleto, pix, etc
            $table->string('payment_reference')->nullable();
            $table->integer('payment_retries')->default(0);

            // Metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullifyOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullifyOnDelete();

            // Indexes
            $table->index('company_id');
            $table->index('renews_at');
            $table->index(['status', 'renews_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
