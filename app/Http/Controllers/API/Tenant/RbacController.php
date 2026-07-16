<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;
use App\Models\Master\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RbacController extends Controller
{
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

        return response()->json($role->load('permissions'), 201);
    }

    /**
     * Assign a role to a tenant user.
     */
    public function assignRole(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'role_name' => 'required|string|exists:tenant.roles,name',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->assignTenantRole($request->role_name);

        return response()->json(['message' => "Papel '{$request->role_name}' atribuído com sucesso ao usuário."]);
    }

    /**
     * Revoke a role from a tenant user.
     */
    public function revokeRole(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'role_name' => 'required|string|exists:tenant.roles,name',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->revokeTenantRole($request->role_name);

        return response()->json(['message' => "Papel '{$request->role_name}' removido com sucesso do usuário."]);
    }
}
