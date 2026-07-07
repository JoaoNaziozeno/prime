<?php

namespace Database\Factories\Master;

use App\Models\Master\Company;
use App\Models\Master\Tenant;
use App\Models\Master\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'slug' => $this->faker->slug(),
            'database_name' => 'prime_tenant_' . str_replace('-', '_', $this->faker->slug()),
            'hostname' => null,
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_username' => 'root',
            'db_password' => '',
            'db_driver' => 'mysql',
            'status' => 'setup',
            'activated_at' => null,
            'settings' => json_encode([
                'language' => 'pt_BR',
                'timezone' => 'America/Sao_Paulo',
            ]),
            'notes' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Tenant ativo
     */
    public function active(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'activated_at' => now(),
            ];
        });
    }

    /**
     * Tenant em setup
     */
    public function setup(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'setup',
            ];
        });
    }

    /**
     * Tenant pausado
     */
    public function paused(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'paused',
            ];
        });
    }

    /**
     * Tenant deletado
     */
    public function deleted(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'deleted',
                'deleted_at' => now(),
            ];
        });
    }

    /**
     * Com hostname customizado
     */
    public function withHostname(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'hostname' => $this->faker->domainName(),
            ];
        });
    }

    /**
     * Com PostgreSQL
     */
    public function withPostgres(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'db_driver' => 'pgsql',
                'db_port' => 5432,
            ];
        });
    }
}
