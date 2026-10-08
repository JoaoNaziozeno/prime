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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('trade_name')->nullable()->after('name');
            $table->string('state_registration', 30)->nullable()->after('cpf_cnpj');
            $table->string('neighborhood')->nullable()->after('street');
            $table->string('contact_name')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'trade_name',
                'state_registration',
                'neighborhood',
                'contact_name',
            ]);
        });
    }
};

