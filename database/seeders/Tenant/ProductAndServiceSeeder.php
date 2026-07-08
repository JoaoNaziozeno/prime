<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\Service;
use App\Models\Tenant\ServiceCategory;
use Illuminate\Database\Seeder;

class ProductAndServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Product Categories
        $categories = [
            ['name' => 'Filtros', 'description' => 'Filtros de ar, óleo, combustível'],
            ['name' => 'Peças do Motor', 'description' => 'Componentes internos do motor'],
            ['name' => 'Sistema de Arrefecimento', 'description' => 'Radiador, mangueiras, bombas'],
            ['name' => 'Sistema Elétrico', 'description' => 'Bateria, alternador, motor de partida'],
            ['name' => 'Sistema de Freios', 'description' => 'Pastilhas, discos, cilindros'],
            ['name' => 'Pneus e Rodas', 'description' => 'Pneus diesel, rodas, aros'],
        ];

        $productCategories = [];
        foreach ($categories as $cat) {
            $productCategories[] = ProductCategory::create($cat);
        }

        // Sample Products
        $products = [
            ['category' => 0, 'name' => 'Filtro de Ar Diesel', 'sku' => 'FA-001', 'unit_price' => 150.00, 'cost_price' => 75.00, 'stock' => 50],
            ['category' => 0, 'name' => 'Filtro de Óleo Pesado', 'sku' => 'FO-002', 'unit_price' => 200.00, 'cost_price' => 100.00, 'stock' => 40],
            ['category' => 0, 'name' => 'Filtro de Combustível', 'sku' => 'FC-003', 'unit_price' => 180.00, 'cost_price' => 90.00, 'stock' => 35],
            ['category' => 1, 'name' => 'Junta do Cabeçote', 'sku' => 'JC-004', 'unit_price' => 450.00, 'cost_price' => 225.00, 'stock' => 20],
            ['category' => 1, 'name' => 'Virabrequim', 'sku' => 'VB-005', 'unit_price' => 2500.00, 'cost_price' => 1250.00, 'stock' => 5],
            ['category' => 2, 'name' => 'Radiador', 'sku' => 'RD-006', 'unit_price' => 800.00, 'cost_price' => 400.00, 'stock' => 15],
            ['category' => 2, 'name' => 'Mangueira Refrigeração', 'sku' => 'MR-007', 'unit_price' => 85.00, 'cost_price' => 42.50, 'stock' => 60],
            ['category' => 3, 'name' => 'Bateria Diesel 140A', 'sku' => 'BD-008', 'unit_price' => 950.00, 'cost_price' => 475.00, 'stock' => 25],
            ['category' => 3, 'name' => 'Alternador Pesado', 'sku' => 'AL-009', 'unit_price' => 1200.00, 'cost_price' => 600.00, 'stock' => 10],
            ['category' => 4, 'name' => 'Pastilha de Freio Dianteira', 'sku' => 'PF-010', 'unit_price' => 320.00, 'cost_price' => 160.00, 'stock' => 45],
            ['category' => 4, 'name' => 'Disco de Freio', 'sku' => 'DF-011', 'unit_price' => 420.00, 'cost_price' => 210.00, 'stock' => 30],
            ['category' => 5, 'name' => 'Pneu Radial 295/75R22.5', 'sku' => 'PR-012', 'unit_price' => 650.00, 'cost_price' => 325.00, 'stock' => 20],
        ];

        foreach ($products as $prod) {
            Product::create([
                'category_id' => $productCategories[$prod['category']]->id,
                'name' => $prod['name'],
                'sku' => $prod['sku'],
                'unit_price' => $prod['unit_price'],
                'cost_price' => $prod['cost_price'],
                'stock_quantity' => $prod['stock'],
                'min_stock_level' => 10,
                'max_stock_level' => $prod['stock'] * 2,
                'unit_of_measure' => 'UND',
            ]);
        }

        // Service Categories
        $serviceCategories = [
            ['name' => 'Manutenção Preventiva', 'description' => 'Serviços de manutenção regular'],
            ['name' => 'Reparos do Motor', 'description' => 'Reparação de motores diesel'],
            ['name' => 'Diagnóstico', 'description' => 'Serviços de diagnóstico e testes'],
            ['name' => 'Sistema de Transmissão', 'description' => 'Reparos de câmbio e embreagem'],
            ['name' => 'Sistema de Freios', 'description' => 'Serviços de freios'],
            ['name' => 'Serviços Gerais', 'description' => 'Outros serviços'],
        ];

        $svcCategoryModels = [];
        foreach ($serviceCategories as $cat) {
            $svcCategoryModels[] = ServiceCategory::create($cat);
        }

        // Sample Services
        $services = [
            ['category' => 0, 'name' => 'Troca de Óleo e Filtros', 'code' => 'S-001', 'price' => 250.00, 'hours' => 1.0],
            ['category' => 0, 'name' => 'Alinhamento de Direção', 'code' => 'S-002', 'price' => 350.00, 'hours' => 1.5],
            ['category' => 0, 'name' => 'Inspeção Geral 50 mil km', 'code' => 'S-003', 'price' => 450.00, 'hours' => 2.0],
            ['category' => 1, 'name' => 'Revisão do Motor Diesel', 'code' => 'S-004', 'price' => 2500.00, 'hours' => 16.0],
            ['category' => 1, 'name' => 'Reparo de Injetor', 'code' => 'S-005', 'price' => 800.00, 'hours' => 4.0],
            ['category' => 2, 'name' => 'Diagnóstico Eletrônico', 'code' => 'S-006', 'price' => 300.00, 'hours' => 1.0],
            ['category' => 2, 'name' => 'Teste de Compressão', 'code' => 'S-007', 'price' => 200.00, 'hours' => 1.0],
            ['category' => 3, 'name' => 'Reparo de Câmbio', 'code' => 'S-008', 'price' => 1800.00, 'hours' => 12.0],
            ['category' => 4, 'name' => 'Revisão de Freios', 'code' => 'S-009', 'price' => 400.00, 'hours' => 2.0],
            ['category' => 4, 'name' => 'Sangria de Freios', 'code' => 'S-010', 'price' => 150.00, 'hours' => 0.5],
            ['category' => 5, 'name' => 'Limpeza Interna', 'code' => 'S-011', 'price' => 100.00, 'hours' => 0.5],
        ];

        foreach ($services as $svc) {
            Service::create([
                'category_id' => $svcCategoryModels[$svc['category']]->id,
                'name' => $svc['name'],
                'code' => $svc['code'],
                'base_price' => $svc['price'],
                'estimated_hours' => $svc['hours'],
            ]);
        }
    }
}
