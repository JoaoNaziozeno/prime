<?php

namespace Database\Seeders\Tenant;

use App\Enums\Tenant\CashRegisterType;
use App\Models\Tenant\CashRegister;
use App\Models\Tenant\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CashManagementSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Métodos de Pagamento Básicos
        $methods = [
            ['name' => 'Dinheiro em Espécie', 'code' => 'cash'],
            ['name' => 'PIX Dinâmico / QR Code', 'code' => 'pix'],
            ['name' => 'Cartão de Crédito (POS)', 'code' => 'credit_card'],
            ['name' => 'Cartão de Débito (POS)', 'code' => 'debit_card'],
            ['name' => 'Boleto Bancário / Faturado', 'code' => 'bank_slip'],
            ['name' => 'Cheque sob Custódia', 'code' => 'check'],
        ];

        foreach ($methods as $m) {
            PaymentMethod::firstOrCreate(
                ['code' => $m['code']],
                ['id' => (string) Str::uuid(), 'name' => $m['name'], 'is_active' => true]
            );
        }

        // 2. Caixa Central / Cofre Geral
        CashRegister::firstOrCreate(
            ['is_central' => true],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Caixa Central / Cofre Matriz',
                'type' => CashRegisterType::CENTRAL,
                'is_central' => true,
                'is_active' => true,
                'max_cash_limit' => null, // Sem teto para o cofre central
            ]
        );

        // 3. Guichês Operacionais de Usuários
        CashRegister::firstOrCreate(
            ['name' => 'Guichê 1 - Balcão Recepção'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Guichê 1 - Balcão Recepção',
                'type' => CashRegisterType::USER_STATION,
                'is_central' => false,
                'is_active' => true,
                'max_cash_limit' => 2000.00, // Alerta em R$ 2.000,00
            ]
        );

        CashRegister::firstOrCreate(
            ['name' => 'Guichê 2 - Venda de Peças'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Guichê 2 - Venda de Peças',
                'type' => CashRegisterType::USER_STATION,
                'is_central' => false,
                'is_active' => true,
                'max_cash_limit' => 2500.00,
            ]
        );
    }
}
