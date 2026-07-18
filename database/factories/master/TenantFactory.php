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
            'database_name' => 'prime_tenant_' . substr(str_replace('-', '_', $this->faker->unique()->slug(3)), 0, 30),
            'hostname' => null,
            'db_host' => env('DB_HOST', '127.0.0.1'),
            'db_port' => env('DB_PORT', 3306),
            'db_username' => env('DB_USERNAME', 'root'),
            'db_password' => env('DB_PASSWORD', ''),
            'db_driver' => env('DB_CONNECTION', 'mysql'),
            'status' => 'setup',
            'activated_at' => null,
            'settings' => [
                'language' => 'pt_BR',
                'timezone' => 'America/Sao_Paulo',
            ],
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
