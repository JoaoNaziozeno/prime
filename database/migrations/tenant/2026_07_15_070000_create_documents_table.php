<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('documentable_type')->index();
            $table->string('documentable_id')->index();
            $table->string('title')->index();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->unsignedInteger('file_size');
            $table->string('document_type')->index();
            $table->date('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
