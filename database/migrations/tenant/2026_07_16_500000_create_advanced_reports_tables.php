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
        // 1. custom_reports
        Schema::create('custom_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('model_type', ['orders', 'financial', 'inventory', 'maintenance'])->default('orders');
            $table->json('columns'); // array of field names
            $table->json('filters'); // object/array of key-value criteria
            $table->string('group_by')->nullable();
            $table->uuid('created_by')->index();
            $table->timestamps();
        });

        // 2. scheduled_reports
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('custom_report_id')->index();
            $table->uuid('user_id')->index();
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->string('email_recipient');
            $table->dateTime('last_sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('custom_report_id')->references('id')->on('custom_reports')->onDelete('cascade');
        });

        // 3. daily_metrics_snapshots
        Schema::create('daily_metrics_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('snapshot_date')->unique();
            $table->decimal('revenue', 12, 2)->default(0.00);
            $table->decimal('cost', 12, 2)->default(0.00);
            $table->decimal('margin', 12, 2)->default(0.00);
            $table->integer('orders_created')->default(0);
            $table->integer('orders_completed')->default(0);
            $table->decimal('nps_average', 5, 2)->default(0.00);
            $table->integer('defects_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_metrics_snapshots');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('custom_reports');
    }
};
