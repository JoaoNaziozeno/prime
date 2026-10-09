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
        Schema::table('vehicles', function (Blueprint $table) {
            // Vínculo com o Cliente Proprietário (PF ou PJ)
            $table->unsignedBigInteger('customer_id')->nullable()->after('id')->index();

            // Número / Código de Frota Interna do Cliente (ex: "Caminhão 102", "Cavalo 04")
            $table->string('fleet_number', 50)->nullable()->after('plate')->index();

            // Especificações Técnicas para Linha Pesada / Diesel
            $table->string('fuel_type', 30)->nullable()->after('color');       // Diesel S10, Diesel Comum, Arla 32, Flex, GNV, Elétrico
            $table->string('engine_type', 100)->nullable()->after('fuel_type');  // Ex: "OM 457 LA", "Scania DC13", "Volvo D13C"
            $table->string('axles', 50)->nullable()->after('engine_type');       // Ex: "4x2 (Toco)", "6x2 (Trucado)", "6x4 (Traçado)", "8x2"
            $table->string('body_type', 80)->nullable()->after('axles');         // Ex: "Cavalo Mecânico", "Baú Sider", "Caçamba", "Graneleiro"

            // Chave estrangeira para clientes
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id', 'status']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['fleet_number']);

            $table->dropColumn([
                'customer_id',
                'fleet_number',
                'fuel_type',
                'engine_type',
                'axles',
                'body_type',
            ]);
        });
    }
};

