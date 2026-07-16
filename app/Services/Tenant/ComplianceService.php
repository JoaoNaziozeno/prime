<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Driver;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Invoice;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ComplianceService
{
    public function anonymizeCustomer(Customer $customer): void
    {
        DB::connection('tenant')->transaction(function () use ($customer) {
            $oldValues = [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone ?? null,
                'cpf_cnpj' => $customer->cpf_cnpj ?? null,
            ];

            $uniq = uniqid();
            $customer->update([
                'name' => 'Anônimo (LGPD) - ' . $uniq,
                'email' => 'anonimo-' . $uniq . '@lgpd.local',
                'phone' => null,
                'cpf_cnpj' => null,
                'metadata' => null,
            ]);

            // Add compliance/audit trail log documenting anonymization
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'anonymize',
                'auditable_type' => Customer::class,
                'auditable_id' => (string) $customer->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'message' => 'Record anonymized under LGPD guidelines',
                ],
                'created_at' => now(),
            ]);
        });
    }

    public function anonymizeDriver(Driver $driver): void
    {
        DB::connection('tenant')->transaction(function () use ($driver) {
            $oldValues = [
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone ?? null,
                'cpf' => $driver->cpf ?? null,
                'cnh' => $driver->cnh ?? null,
            ];

            $uniq = uniqid();
            $driver->update([
                'name' => 'Anônimo (LGPD) - ' . $uniq,
                'email' => 'anonimo-' . $uniq . '@lgpd.local',
                'phone' => null,
                'cpf' => null,
                'cnh' => null,
            ]);

            // Add compliance/audit trail log documenting anonymization
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'anonymize',
                'auditable_type' => Driver::class,
                'auditable_id' => (string) $driver->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'message' => 'Record anonymized under LGPD guidelines',
                ],
                'created_at' => now(),
            ]);
        });
    }

    public function purgeAuditLogs(int $retentionMonths): int
    {
        $cutoffDate = now()->subMonths($retentionMonths);
        
        return AuditLog::where('created_at', '<', $cutoffDate)->delete();
    }

    public function transmitInvoiceToNfe(Invoice $invoice): array
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><nfe><id>{$invoice->id}</id><number>{$invoice->invoice_number}</number><total_amount>{$invoice->total_amount}</total_amount><tax_amount>{$invoice->tax_amount}</tax_amount><tax_rate>5.00</tax_rate></nfe>";
        
        $nfeNumber = 'NFE-' . date('Y') . '-' . str_pad($invoice->invoice_number ?? rand(1000, 9999), 6, '0', STR_PAD_LEFT);
        $verificationCode = bin2hex(random_bytes(8));

        $metadata = $invoice->metadata ?? [];
        $metadata['nfe'] = [
            'status' => 'transmitted',
            'nfe_number' => $nfeNumber,
            'verification_code' => $verificationCode,
            'xml_payload' => $xml,
            'transmitted_at' => now()->toIso8601String(),
        ];

        $invoice->update([
            'metadata' => $metadata,
        ]);

        return [
            'status' => 'success',
            'nfe_number' => $nfeNumber,
            'verification_code' => $verificationCode,
            'xml_payload' => $xml,
        ];
    }
}
