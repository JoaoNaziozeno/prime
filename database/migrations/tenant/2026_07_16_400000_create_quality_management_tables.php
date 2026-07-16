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
        // 1. qa_templates
        Schema::create('qa_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('items'); // array of strings (checklist items)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. qa_inspections
        Schema::create('qa_inspections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_of_service_id')->index();
            $table->unsignedBigInteger('qa_template_id')->nullable()->index();
            $table->uuid('inspector_id')->index();
            $table->enum('status', ['pending', 'passed', 'failed'])->default('pending');
            $table->json('items_checked'); // array of objects {name, status, notes}
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('cascade');
            $table->foreign('qa_template_id')->references('id')->on('qa_templates')->onDelete('set null');
        });

        // 3. qa_defects
        Schema::create('qa_defects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('qa_inspection_id')->index();
            $table->text('description');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'in_rework', 'resolved'])->default('open');
            $table->dateTime('resolved_at')->nullable();
            $table->uuid('resolved_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('qa_inspection_id')->references('id')->on('qa_inspections')->onDelete('cascade');
        });

        // 4. order_feedback
        Schema::create('order_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_of_service_id')->unique();
            $table->integer('rating');
            $table->integer('nps_score');
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('cascade');
        });

        // 5. warranties
        Schema::create('warranties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_of_service_id')->index();
            $table->enum('type', ['full', 'parts', 'labor'])->default('full');
            $table->integer('duration_days');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('terms')->nullable();
            $table->enum('status', ['active', 'expired', 'claimed'])->default('active');
            $table->timestamps();

            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('order_feedback');
        Schema::dropIfExists('qa_defects');
        Schema::dropIfExists('qa_inspections');
        Schema::dropIfExists('qa_templates');
    }
};
