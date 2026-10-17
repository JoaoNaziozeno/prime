<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cadastros de Caixas Físicos / Lógicos (Central, Guichês, Pontos de Atendimento)
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->enum('type', ['central', 'user_station', 'mobile'])->default('user_station')->index();
            $table->boolean('is_central')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('max_cash_limit', 12, 2)->nullable()->comment('Teto de gaveta para alerta de sangria preventiva');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        // 2. Sessões / Turnos de Caixa por Usuário
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_register_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('status', ['open', 'closing', 'closed', 'contested'])->default('open')->index();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0)->comment('Fundo de troco inicial');
            $table->decimal('calculated_cash_balance', 12, 2)->default(0)->comment('Saldo apurado pelo sistema em dinheiro');
            $table->decimal('reported_cash_balance', 12, 2)->nullable()->comment('Saldo contado no fechamento cego');
            $table->decimal('cash_difference', 12, 2)->nullable()->comment('Divergência: sobra positiva, falta negativa');
            $table->text('closing_notes')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable()->index();
            $table->unsignedBigInteger('verified_by')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cash_register_id')->references('id')->on('cash_registers')->onDelete('cascade');
        });

        // 3. Movimentações Financeiras de Caixa (Entradas e Saídas)
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_session_id')->index();
            $table->uuid('order_of_service_id')->nullable()->index();
            $table->uuid('invoice_id')->nullable()->index();
            $table->uuid('payment_method_id')->nullable()->index();
            $table->enum('type', ['inflow', 'outflow'])->index();
            $table->enum('category', [
                'os_payment',
                'os_advance',
                'change_supply',
                'bleed_sangria',
                'petty_expense',
                'transfer_in',
                'transfer_out',
                'discrepancy_adjustment'
            ])->index();
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->string('receipt_number')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->onDelete('cascade');
            $table->foreign('order_of_service_id')->references('id')->on('orders_of_service')->onDelete('set null');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
        });

        // 4. Transferências entre Caixas (Sangrias, Suprimentos e P2P com Duplo Aceite)
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('protocol_number')->unique();
            $table->uuid('source_session_id')->index();
            $table->uuid('destination_session_id')->index();
            $table->enum('transfer_type', [
                'bleed_to_central',
                'supply_from_central',
                'p2p_user',
                'bank_deposit'
            ])->index();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending')->index();
            $table->unsignedBigInteger('requested_by')->index();
            $table->unsignedBigInteger('processed_by')->nullable()->index();
            $table->dateTime('requested_at');
            $table->dateTime('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('receipt_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('source_session_id')->references('id')->on('cash_sessions')->onDelete('cascade');
            $table->foreign('destination_session_id')->references('id')->on('cash_sessions')->onDelete('cascade');
        });

        // 5. Contagem de Cédulas e Moedas (Fechamento Cego e Abertura)
        Schema::create('cash_denominations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_session_id')->index();
            $table->enum('count_type', ['opening', 'blind_closing'])->default('blind_closing')->index();
            $table->decimal('denomination', 8, 2);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_denominations');
        Schema::dropIfExists('cash_transfers');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');
    }
};
