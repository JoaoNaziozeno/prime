<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Master\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Obter o nome da conexão central de banco de dados de forma dinâmica.
     * Necessário para compatibilidade com ambiente de testes (SQLite in-memory) e produção (MySQL).
     */
    protected function getCentralConnection(): string
    {
        return config('tenancy.database.central_connection', 'central');
    }

    /**
     * GET /api/users - List all users associated with the tenant
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $company = tenant()->company;
        if (!$company) {
            return response()->json(['error' => 'Inquilino sem empresa associada.'], 400);
        }

        $query = $company->users();

        if ($request->has('search') && !empty($request->get('search'))) {
            $term = $request->get('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        $users = $query->paginate(20);

        // Anexar os papéis (roles) do inquilino para cada usuário
        foreach ($users as $user) {
            $roleNames = DB::connection('tenant')
                ->table('tenant_user_roles')
                ->join('roles', 'tenant_user_roles.role_id', '=', 'roles.id')
                ->where('tenant_user_roles.user_id', $user->id)
                ->pluck('roles.name')
                ->toArray();
            $user->tenant_roles = $roleNames;
        }

        return response()->json($users);
    }

    /**
     * POST /api/users - Create and link a new user to the tenant
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $centralConn = $this->getCentralConnection();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:{$centralConn}.users,email",
            'password' => 'required|string|min:6',
            'role' => 'nullable|string|in:admin,user',
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:tenant.roles,name',
        ]);

        $company = tenant()->company;
        if (!$company) {
            return response()->json(['error' => 'Inquilino sem empresa associada.'], 400);
        }

        $user = DB::connection($centralConn)->transaction(function () use ($validated, $company, $request, $centralConn) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'role' => $validated['role'] ?? 'user',
                'is_active' => true,
            ]);

            // Link user to the company
            $company->users()->attach($user->id, ['role' => $validated['role'] ?? 'user']);

            // Link tenant roles
            if ($request->has('roles')) {
                foreach ($request->roles as $roleName) {
                    $user->assignTenantRole($roleName);
                }
            }

            return $user;
        });

        // Retornar os papéis atribuídos
        $roleNames = DB::connection('tenant')
            ->table('tenant_user_roles')
            ->join('roles', 'tenant_user_roles.role_id', '=', 'roles.id')
            ->where('tenant_user_roles.user_id', $user->id)
            ->pluck('roles.name')
            ->toArray();
        $user->tenant_roles = $roleNames;

        return response()->json($user, 201);
    }

    /**
     * GET /api/users/{id} - Show specific user details
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        // Verificar se usuário pertence à empresa
        $company = tenant()->company;
        if (!$company || !$company->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['error' => 'Usuário não pertence a esta empresa.'], 403);
        }

        $roleNames = DB::connection('tenant')
            ->table('tenant_user_roles')
            ->join('roles', 'tenant_user_roles.role_id', '=', 'roles.id')
            ->where('tenant_user_roles.user_id', $user->id)
            ->pluck('roles.name')
            ->toArray();
        $user->tenant_roles = $roleNames;

        return response()->json($user);
    }

    /**
     * PUT /api/users/{id} - Update user details and roles
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        // Verificar se usuário pertence à empresa
        $company = tenant()->company;
        if (!$company || !$company->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['error' => 'Usuário não pertence a esta empresa.'], 403);
        }

        $centralConn = $this->getCentralConnection();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:{$centralConn}.users,email," . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string|in:admin,user',
            'is_active' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:tenant.roles,name',
        ]);

        DB::connection($centralConn)->transaction(function () use ($validated, $company, $request, $user, $centralConn) {
            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            if (!empty($validated['password'])) {
                $updateData['password'] = bcrypt($validated['password']);
            }

            if (isset($validated['is_active'])) {
                $updateData['is_active'] = $validated['is_active'];
            }

            if (isset($validated['role'])) {
                $updateData['role'] = $validated['role'];
                // Update role in pivot table as well
                $company->users()->updateExistingPivot($user->id, ['role' => $validated['role']]);
            }

            $user->update($updateData);

            // Sync tenant roles
            if ($request->has('roles')) {
                DB::connection('tenant')
                    ->table('tenant_user_roles')
                    ->where('user_id', $user->id)
                    ->delete();

                foreach ($request->roles as $roleName) {
                    $user->assignTenantRole($roleName);
                }
            }
        });

        $roleNames = DB::connection('tenant')
            ->table('tenant_user_roles')
            ->join('roles', 'tenant_user_roles.role_id', '=', 'roles.id')
            ->where('tenant_user_roles.user_id', $user->id)
            ->pluck('roles.name')
            ->toArray();
        $user->tenant_roles = $roleNames;

        return response()->json($user);
    }

    /**
     * DELETE /api/users/{id} - Remove user link from company
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        // Verificar se usuário pertence à empresa
        $company = tenant()->company;
        if (!$company || !$company->users()->where('users.id', $user->id)->exists()) {
            return response()->json(['error' => 'Usuário não pertence a esta empresa.'], 403);
        }

        $centralConn = $this->getCentralConnection();

        DB::connection($centralConn)->transaction(function () use ($company, $user) {
            // Desassociar da empresa
            $company->users()->detach($user->id);

            // Limpar papéis do inquilino
            DB::connection('tenant')
                ->table('tenant_user_roles')
                ->where('user_id', $user->id)
                ->delete();
        });

        return response()->json(['message' => 'Usuário desassociado da empresa com sucesso.']);
    }
}
