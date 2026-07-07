<?php

namespace Database\Seeders\Master;

use App\Models\Master\Plan;
use App\Models\Master\User;
use App\Models\Master\Company;
use App\Models\Master\Subscription;
use App\Models\Master\Tenant;
use Illuminate\Database\Seeder;

class MasterDatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Criar planos padrão
        $basicPlan = Plan::factory()->basic()->create();
        $proPlan = Plan::factory()->professional()->create();
        $enterprisePlan = Plan::factory()->enterprise()->create();

        // Criar super admin
        $superAdmin = User::factory()
            ->superAdmin()
            ->create([
                'name' => 'Super Admin',
                'email' => 'superadmin@prime-erp.local',
                'password' => bcrypt('password'),
            ]);

        // Criar alguns usuários
        $admin = User::factory()
            ->admin()
            ->create([
                'name' => 'Admin',
                'email' => 'admin@prime-erp.local',
                'password' => bcrypt('password'),
            ]);

        User::factory(5)->user()->create();

        // Criar empresas
        $company1 = Company::factory()
            ->active()
            ->create([
                'name' => 'Empresa Demo',
                'email' => 'contato@empresa-demo.local',
                'cnpj' => '11222333000181',
                'owner_id' => $superAdmin->id,
                'created_by' => $superAdmin->id,
            ]);

        $company2 = Company::factory()
            ->active()
            ->create([
                'owner_id' => $admin->id,
                'created_by' => $admin->id,
            ]);

        // Criar assinaturas
        Subscription::factory()
            ->active()
            ->create([
                'company_id' => $company1->id,
                'plan_id' => $basicPlan->id,
                'created_by' => $superAdmin->id,
            ]);

        Subscription::factory()
            ->active()
            ->create([
                'company_id' => $company2->id,
                'plan_id' => $proPlan->id,
                'created_by' => $admin->id,
            ]);

        // Criar alguns tenants
        $tenant1 = Tenant::factory()
            ->active()
            ->create([
                'company_id' => $company1->id,
                'slug' => 'empresa-demo',
                'database_name' => 'prime_tenant_empresa_demo',
                'created_by' => $superAdmin->id,
            ]);

        $tenant2 = Tenant::factory()
            ->setup()
            ->create([
                'company_id' => $company2->id,
                'created_by' => $admin->id,
            ]);

        // Mais empresas para teste
        Company::factory(5)
            ->active()
            ->create([
                'created_by' => $superAdmin->id,
            ]);
    }
}
