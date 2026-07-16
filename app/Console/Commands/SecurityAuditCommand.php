<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SecurityAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prime:security-audit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit ERP security configuration, environment variables, permissions, encryption and rate limiters';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=========================================');
        $this->info('   ERP SaaS - Relatório de Auditoria     ');
        $this->info('=========================================');

        $warnings = 0;
        $failures = 0;

        // 1. Check APP_DEBUG
        if (config('app.debug')) {
            $this->warn('[WARN] APP_DEBUG está ativado! Recomendado desativar em produção.');
            $warnings++;
        } else {
            $this->info('[OK] APP_DEBUG está desativado.');
        }

        // 2. Check APP_KEY
        $key = config('app.key');
        if (empty($key)) {
            $this->error('[FAIL] APP_KEY está vazia ou não configurada!');
            $failures++;
        } else {
            $this->info('[OK] APP_KEY configurada corretamente.');
        }

        // 3. Check Write Permissions
        $storageApp = storage_path('app');
        $storageFramework = storage_path('framework');
        $storageLogs = storage_path('logs');
        $storageRoot = storage_path();

        File::ensureDirectoryExists($storageApp);
        File::ensureDirectoryExists($storageFramework);
        File::ensureDirectoryExists($storageLogs);

        $storageWritable = $this->isFolderWritable($storageApp) ||
                           $this->isFolderWritable($storageFramework) ||
                           $this->isFolderWritable($storageLogs) ||
                           $this->isFolderWritable($storageRoot);

        if ($storageWritable) {
            $this->info('[OK] Pasta storage possui permissão de escrita.');
        } else {
            $this->error('[FAIL] Pasta storage não possui permissão de escrita!');
            $failures++;
        }

        $bootstrapCache = base_path('bootstrap/cache');
        File::ensureDirectoryExists($bootstrapCache);

        if ($this->isFolderWritable($bootstrapCache)) {
            $this->info('[OK] Pasta bootstrap/cache possui permissão de escrita.');
        } else {
            $this->error('[FAIL] Pasta bootstrap/cache não possui permissão de escrita!');
            $failures++;
        }

        // 4. Check Model Encryption casts
        try {
            $customerCasts = (new \App\Models\Tenant\Customer)->getCasts();
            $invoiceCasts = (new \App\Models\Tenant\Invoice)->getCasts();

            if (isset($customerCasts['notes']) && $customerCasts['notes'] === 'encrypted') {
                $this->info('[OK] Criptografia de notas no modelo Customer está ativa.');
            } else {
                $this->warn('[WARN] Notas do Customer não estão marcadas como encrypted.');
                $warnings++;
            }

            if (isset($invoiceCasts['notes']) && $invoiceCasts['notes'] === 'encrypted') {
                $this->info('[OK] Criptografia de notas no modelo Invoice está ativa.');
            } else {
                $this->warn('[WARN] Notas do Invoice não estão marcadas como encrypted.');
                $warnings++;
            }
        } catch (\Exception $e) {
            $this->warn('[WARN] Não foi possível verificar as configurações de criptografia nos modelos: ' . $e->getMessage());
            $warnings++;
        }

        // 5. Check Rate Limiting in bootstrap/app.php
        $bootstrapApp = base_path('bootstrap/app.php');
        if (File::exists($bootstrapApp)) {
            $content = File::get($bootstrapApp);
            if (str_contains($content, 'throttle:60,1')) {
                $this->info('[OK] Rate limit (throttle:60,1) está registrado para APIs.');
            } else {
                $this->warn('[WARN] Rate limit geral de APIs (throttle) não encontrado no bootstrap/app.php.');
                $warnings++;
            }
        } else {
            $this->warn('[WARN] Arquivo bootstrap/app.php não pôde ser analisado.');
            $warnings++;
        }

        $this->info('=========================================');
        $this->info("Auditoria concluída: {$failures} falhas, {$warnings} avisos.");
        $this->info('=========================================');

        return $failures > 0 ? 1 : 0;
    }

    /**
     * Check if folder is writable in a cross-platform reliable way.
     */
    protected function isFolderWritable(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }
        $tempFile = $path . '/' . uniqid('audit_write_test_') . '.tmp';
        $written = @file_put_contents($tempFile, 'test');
        if ($written !== false) {
            @unlink($tempFile);
            return true;
        }
        return false;
    }
}
