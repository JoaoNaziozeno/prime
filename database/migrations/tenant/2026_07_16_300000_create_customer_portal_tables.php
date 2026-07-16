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
        // 1. personal_access_tokens inside tenant database (for Sanctum support on customers)
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // 2. customer_login_tokens
        Schema::create('customer_login_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('token')->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });

        // 3. order_messages
        Schema::create('order_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_of_service_id')->index();
            $table->enum('sender_type', ['user', 'customer'])->index();
            $table->string('sender_id')->index();
            $table->text('message');
            $table->timestamps();

            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_messages');
        Schema::dropIfExists('customer_login_tokens');
        Schema::dropIfExists('personal_access_tokens');
    }
};
