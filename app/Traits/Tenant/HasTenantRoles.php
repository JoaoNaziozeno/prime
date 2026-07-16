<?php

namespace App\Traits\Tenant;

use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasTenantRoles
{
    /**
     * Get user's roles within the current tenant context.
     */
    public function tenantRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'tenant_user_roles', 'user_id', 'role_id')
            ->where($this->qualifyColumn('id'), '=', $this->id); // Workaround to force cross-connection pivot
    }

    /**
     * Check if user has permission in the current tenant.
     * Admins and Super Admins bypass checks.
     */
    public function hasTenantPermission(string $permissionName): bool
    {
        if ($this->isSuperAdmin() || $this->role === 'admin') {
            return true;
        }

        // Retrieve tenant roles of this user
        // Using direct query to avoid belongsToMany cross-connection join issues in some DB drivers
        $roleIds = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('tenant_user_roles')
            ->where('user_id', $this->id)
            ->pluck('role_id')
            ->toArray();

        if (empty($roleIds)) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('role_permission')
            ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->whereIn('role_permission.role_id', $roleIds)
            ->where('permissions.name', $permissionName)
            ->exists();
    }

    /**
     * Assign a tenant role to the user.
     */
    public function assignTenantRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        // Avoid duplicate entry error using updateOrInsert or checking existence
        \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('tenant_user_roles')
            ->updateOrInsert([
                'user_id' => $this->id,
                'role_id' => $role->id,
            ]);
    }

    /**
     * Revoke a tenant role from the user.
     */
    public function revokeTenantRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('tenant_user_roles')
            ->where('user_id', $this->id)
            ->where('role_id', $role->id)
            ->delete();
    }
}
