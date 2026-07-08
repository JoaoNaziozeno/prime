<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->index();
            $table->string('name')->index();
            $table->string('code')->unique()->index();
            $table->text('description')->nullable();
            $table->decimal('base_price', 12, 2);
            $table->decimal('estimated_hours', 6, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('service_categories')->onDelete('cascade');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
