<?php

namespace App\Traits\Tenant;

use App\Models\Tenant\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Boot the trait and register Eloquent event listeners.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->logAudit('create', null, $model->getAuditAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            
            // Exclude common timestamp columns if they are the only changes
            unset($changes['updated_at']);
            if (empty($changes)) {
                return;
            }

            $old = [];
            $new = [];

            foreach ($changes as $key => $value) {
                $old[$key] = $model->getOriginal($key);
                $new[$key] = $value;
            }

            $model->logAudit('update', $model->getAuditAttributes($old), $model->getAuditAttributes($new));
        });

        static::deleted(function (Model $model) {
            $model->logAudit('delete', $model->getAuditAttributes($model->getOriginal()), null);
        });
    }

    /**
     * Log the audit trace into database.
     */
    protected function logAudit(string $action, ?array $old, ?array $new): void
    {
        // Don't write logs if not in tenant context or during setup
        if (!function_exists('tenant') || !tenant()) {
            return;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => (string) $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Filter sensitive fields from audit logs.
     */
    protected function getAuditAttributes(array $attributes): array
    {
        $sensitive = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
        
        return array_filter($attributes, function ($key) use ($sensitive) {
            return !in_array($key, $sensitive);
        }, ARRAY_FILTER_USE_KEY);
    }
}
