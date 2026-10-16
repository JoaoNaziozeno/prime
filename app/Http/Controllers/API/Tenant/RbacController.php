<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;
use App\Models\Master\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RbacController extends Controller
{
    /**
     * Only administrators may change roles and role assignments.
     */
    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user() && $request->user()->isAdmin(),
            403,
            'Não autorizado.'
        );
    }

    /**
     * Find a user that belongs to the same company as the acting admin,
     * so one tenant can never change roles of another tenant's users.
     */
    private function findTenantUser(Request $request, int $userId): User
    {
        $actor = $request->user();
        $user = User::findOrFail($userId);

        if ($actor->role !== 'super_admin') {
            $companyIds = $actor->companies()->pluck('companies.id');
            abort_unless(
                $user->companies()->whereIn('companies.id', $companyIds)->exists(),
                404
            );
        }

        return $user;
    }

    /**
     * List all roles in the tenant.
     */
    public function indexRoles(): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    /**
     * List all permissions.
     */
    public function indexPermissions(): JsonResponse
    {
        $permissions = Permission::all();
        return response()->json($permissions);
    }

    /**
     * Create a new role with permissions.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->validate([
            'name' => 'required|string|unique:tenant.roles,name',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'required|string|exists:tenant.permissions,name',
        ]);

        $role = DB::connection('tenant')->transaction(function () use ($request) {
            $role = Role::create([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            $permissionIds = Permission::whereIn('name', $request->permissions)
                ->pluck('id')
                ->toArray();

            $role->permissions()->sync($permissionIds);

            return $role;
        });

        Log::info("RBAC: Novo papel '{$role->name}' criado pelo admin {$request->user()->id}");

        return response()->json($role->load('permissions'), 201);
    }

    /**
     * Assign a role to a tenant user.
     */
    public function assignRole(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->validate([
            'user_id' => 'required|integer',
            'role_name' => 'required|string|exists:tenant.roles,name',
        ]);

        $user = $this->findTenantUser($request, (int) $request->user_id);
        $user->assignTenantRole($request->role_name);

        Log::info("RBAC: Papel '{$request->role_name}' atribuído ao usuário {$user->id} pelo admin {$request->user()->id}");

        return response()->json(['message' => "Papel '{$request->role_name}' atribuído com sucesso ao usuário."]);
    }

    /**
     * Revoke a role from a tenant user.
     */
    public function revokeRole(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $request->validate([
            'user_id' => 'required|integer',
            'role_name' => 'required|string|exists:tenant.roles,name',
        ]);

        $user = $this->findTenantUser($request, (int) $request->user_id);

        // Prevenção de auto-desativação acidental de administrador
        if ($user->id === $request->user()->id && in_array($request->role_name, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Não é permitido revogar seu próprio papel de administrador.'], 422);
        }

        $user->revokeTenantRole($request->role_name);

        Log::info("RBAC: Papel '{$request->role_name}' revogado do usuário {$user->id} pelo admin {$request->user()->id}");

        return response()->json(['message' => "Papel '{$request->role_name}' removido com sucesso do usuário."]);
    }
}
