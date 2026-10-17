<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Driver;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ComplianceService
{
    public function anonymizeCustomer(Customer $customer): void
    {
        DB::connection('tenant')->transaction(function () use ($customer) {
            $customerId = $customer->id;

            $uniq = uniqid('', true);
            $customer->update([
                'name'         => 'Anônimo (LGPD) - ' . $uniq,
                'email'        => 'anonimo-' . $uniq . '@lgpd.local',
                'phone'        => null,
                'cpf_cnpj'     => null,
                'contact_name' => null,
                'trade_name'   => null,
                'address'      => null,
                'city'         => null,
                'state'        => null,
                'zip_code'     => null,
                'notes'        => null,
                'metadata'     => null,
            ]);

            // Registra apenas quais campos foram apagados, SEM os valores originais (LGPD)
            AuditLog::create([
                'user_id'        => auth()->id(),
                'action'         => 'anonymize',
                'auditable_type' => Customer::class,
                'auditable_id'   => (string) $customerId,
                'old_values'     => ['fields_anonymized' => ['name','email','phone','cpf_cnpj','contact_name','trade_name','address','city','state','zip_code','notes']],
                'new_values'     => ['message' => 'Dados pessoais anonimizados conforme LGPD — valores originais não armazenados'],
                'created_at'     => now(),
            ]);

            Log::info('LGPD: cliente anonimizado', ['customer_id' => $customerId, 'by_user' => auth()->id()]);
        });
    }

    public function anonymizeDriver(Driver $driver): void
    {
        DB::connection('tenant')->transaction(function () use ($driver) {
            $driverId = $driver->id;

            $uniq = uniqid('', true);
            $driver->update([
                'name'  => 'Anônimo (LGPD) - ' . $uniq,
                'email' => 'anonimo-' . $uniq . '@lgpd.local',
                'phone' => null,
                'cpf'   => null,
                'cnh'   => null,
            ]);

            AuditLog::create([
                'user_id'        => auth()->id(),
                'action'         => 'anonymize',
                'auditable_type' => Driver::class,
                'auditable_id'   => (string) $driverId,
                'old_values'     => ['fields_anonymized' => ['name','email','phone','cpf','cnh']],
                'new_values'     => ['message' => 'Dados pessoais anonimizados conforme LGPD — valores originais não armazenados'],
                'created_at'     => now(),
            ]);

            Log::info('LGPD: motorista anonimizado', ['driver_id' => $driverId, 'by_user' => auth()->id()]);
        });
    }

    public function purgeAuditLogs(int $retentionMonths): int
    {
        $cutoffDate = now()->subMonths($retentionMonths);

        return AuditLog::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Emissão de NF-e — funcionalidade não implementada.
     *
     * A integração real com a SEFAZ requer um emissor homologado (ex: NFePHP, Focus NF-e).
     * Este método não transmite nada para o fisco e NÃO deve ser usado em produção.
     *
     * @throws \RuntimeException sempre
     */
    public function transmitInvoiceToNfe(Invoice $invoice): array
    {
        throw new \RuntimeException('Emissão de NF-e não implementada. Integre um emissor fiscal homologado antes de usar esta funcionalidade.');
    }
}
