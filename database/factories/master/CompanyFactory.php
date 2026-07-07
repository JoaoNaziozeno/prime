<?php

namespace Database\Factories\Master;

use App\Models\Master\Company;
use App\Models\Master\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'cnpj' => $this->generateCNPJ(),
            'website' => $this->faker->url(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'country' => 'BR',
            'zip_code' => $this->faker->postcode(),
            'status' => 'active',
            'owner_id' => User::factory(),
            'activated_at' => now(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Empresa ativa
     */
    public function active(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
                'activated_at' => now(),
                'suspended_at' => null,
            ];
        });
    }

    /**
     * Empresa inativa
     */
    public function inactive(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'inactive',
            ];
        });
    }

    /**
     * Empresa suspensa
     */
    public function suspended(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'suspended',
                'suspended_at' => now(),
            ];
        });
    }

    /**
     * Gerar CNPJ válido (formato números)
     */
    private function generateCNPJ(): string
    {
        $cnpj = '';
        for ($i = 0; $i < 14; $i++) {
            $cnpj .= rand(0, 9);
        }
        return $cnpj;
    }
}
