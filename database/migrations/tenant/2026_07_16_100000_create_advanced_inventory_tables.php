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
        // 1. suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('cnpj', 20)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. purchase_orders
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->unsignedBigInteger('branch_id')->index();
            $table->enum('status', ['draft', 'sent', 'approved', 'received', 'cancelled'])->default('draft')->index();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('ordered_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        // 3. purchase_order_items
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('purchase_order_id')->index();
            $table->uuid('product_id')->index();
            $table->decimal('quantity', 10, 2);
            $table->decimal('quantity_received', 10, 2)->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // 4. Alter products table
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_serialized')->default(false)->after('sku');
            $table->boolean('has_batches')->default(false)->after('is_serialized');
        });

        // 5. product_batches
        Schema::create('product_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->string('batch_number');
            $table->date('expiration_date')->nullable()->index();
            $table->decimal('initial_quantity', 10, 2);
            $table->decimal('current_quantity', 10, 2)->default(0);
            $table->unsignedBigInteger('purchase_order_item_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->onDelete('set null');
            $table->unique(['product_id', 'batch_number']);
        });

        // 6. product_serials
        Schema::create('product_serials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->string('serial_number');
            $table->enum('status', ['available', 'reserved', 'sold', 'returned'])->default('available')->index();
            $table->unsignedBigInteger('purchase_order_item_id')->nullable()->index();
            $table->uuid('order_product_line_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->onDelete('set null');
            $table->unique(['product_id', 'serial_number']);
        });

        // 7. Alter order_product_lines table
        Schema::table('order_product_lines', function (Blueprint $table) {
            $table->uuid('product_batch_id')->nullable()->index()->after('warehouse_location_id');
            $table->foreign('product_batch_id')->references('id')->on('product_batches')->onDelete('set null');
        });

        // Add foreign key constraint back to order_product_lines on product_serials
        Schema::table('product_serials', function (Blueprint $table) {
            $table->foreign('order_product_line_id')->references('id')->on('order_product_lines')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_serials', function (Blueprint $table) {
            $table->dropForeign(['order_product_line_id']);
        });

        Schema::table('order_product_lines', function (Blueprint $table) {
            $table->dropForeign(['product_batch_id']);
            $table->dropColumn('product_batch_id');
        });

        Schema::dropIfExists('product_serials');
        Schema::dropIfExists('product_batches');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_serialized', 'has_batches']);
        });

        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
